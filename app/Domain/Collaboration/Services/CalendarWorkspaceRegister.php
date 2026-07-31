<?php

namespace App\Domain\Collaboration\Services;

use App\Models\CalendarEvent;
use App\Models\Employee;
use App\Models\User;
use App\Services\Collaboration\CollaborationService;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class CalendarWorkspaceRegister
{
    private const VIEWER_TIMEZONE = 'Asia/Kolkata';
    private const DAY_START_HOUR = 7;
    private const DAY_END_HOUR = 20;

    public function __construct(
        private readonly CollaborationService $collaboration,
        private readonly CompanyScopeService $companyScope,
        private readonly PaginationPolicy $pagination,
        private readonly CalendarEventPresenter $presenter,
    ) {}

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function workspace(User $user, array $filters): array
    {
        $timezone = self::VIEWER_TIMEZONE;
        $view = (string) ($filters['view'] ?? 'month');
        $focusDate = CarbonImmutable::parse($filters['focus_date'] ?? 'today', $timezone)->startOfDay();
        [$localStart, $localEnd] = $this->period($view, $focusDate, $filters);
        $periodStart = $localStart->utc();
        $periodEnd = $localEnd->utc();

        $query = $this->baseQuery($user, $filters);
        $this->applyParticipantScope($query, $user, $filters);
        $summary = $this->summary(clone $query, $timezone);
        $this->applySummaryFilter($query, (string) ($filters['summary'] ?? ''), $timezone);

        $periodEvents = (clone $query)
            ->where('starts_at', '<=', $periodEnd)
            ->where('ends_at', '>=', $periodStart)
            ->orderBy('starts_at')->orderBy('id')->get();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = $this->pagination->workspacePerPage();
        $events = new LengthAwarePaginator(
            $periodEvents->forPage($page, $perPage)->values(),
            $periodEvents->count(), $perPage, $page,
            ['path' => '/collaboration/calendar-events', 'query' => $filters],
        );

        return [
            'events' => $events,
            'period_events' => $periodEvents,
            'summary' => $summary,
            'selected_event' => $this->selectedEvent($user, $filters),
            'focus_date' => $focusDate,
            'period_start' => $localStart,
            'period_end' => $localEnd,
            'period_label' => $this->periodLabel($view, $focusDate),
            'calendar_days' => $this->calendarDays($localStart, $localEnd, $focusDate->month, $periodEvents, $timezone),
            'hours' => range(self::DAY_START_HOUR, self::DAY_END_HOUR),
            'timed_days' => $this->timedDays($view, $focusDate, $localStart, $periodEvents, $timezone),
            'employee_lanes' => $this->employeeLanes($user, $periodEvents),
            'team_lanes' => $this->teamLanes($user, $periodEvents),
            'timezone' => $timezone,
        ];
    }

    /** @param array<string,mixed> $filters */
    // private function baseQuery(User $user, array $filters): Builder
    // {
    //     $query = CalendarEvent::query()->with($this->collaboration->eventRelations());
    //     $this->companyScope->apply($query, $user);

    //     if (! $user->hasPermission('collaboration.view') && ! $user->hasPermission('collaboration.manage')) {
    //         $query->where(fn (Builder $visible) => $visible
    //             ->where('organizer_user_id', $user->id)
    //             ->orWhereHas('attendeeRecords', fn (Builder $attendee) => $attendee->where('user_id', $user->id)));
    //     }

    //     if ($type = (string) ($filters['event_type'] ?? '')) {
    //         $types = match ($type) {
    //             'appointment' => ['appointment', 'site_visit', 'inspection'],
    //             'meeting' => ['meeting', 'interview'],
    //             'follow_up' => ['follow_up', 'payment_follow_up'],
    //             'internal' => ['internal', 'training'],
    //             default => [$type],
    //         };
    //         $query->whereIn('event_type', $types);
    //     }

    //     return $query
    //         ->when($filters['status'] ?? null, fn (Builder $q, string $value) => $q->where('status', $value))
    //         ->when($filters['project_id'] ?? null, fn (Builder $q, int|string $value) => $q->where('project_id', $value))
    //         ->when($filters['priority'] ?? null, fn (Builder $q, string $value) => $q->where('metadata->priority', $value))
    //         ->when($filters['invitation_response'] ?? null, fn (Builder $q, string $value) => $q->whereHas('attendeeRecords', fn (Builder $a) => $a->where('response', $value)))
    //         ->when($filters['q'] ?? null, function (Builder $q, string $value): void {
    //             $search = '%'.trim($value).'%';
    //             $q->where(fn (Builder $nested) => $nested
    //                 ->where('title', 'like', $search)->orWhere('event_number', 'like', $search)
    //                 ->orWhere('description', 'like', $search)->orWhere('location', 'like', $search)
    //                 ->orWhereHas('project', fn (Builder $project) => $project->where('name', 'like', $search)->orWhere('code', 'like', $search))
    //                 ->orWhereHas('organizer', fn (Builder $organizer) => $organizer->where('name', 'like', $search)->orWhere('email', 'like', $search))
    //                 ->orWhereHas('attendeeRecords', fn (Builder $attendee) => $attendee->where('name', 'like', $search)->orWhere('email', 'like', $search)));
    //         });
    // }
    
    private function baseQuery(User $user, array $filters): Builder
    {
        // dump($user->id);
        // dump('role',$user->role);    // a normal user (not manager)
        // dump($user->hasPermission('collaboration.view'));    // likely true
        // dump($user->hasPermission('collaboration.manage'));
        $query = CalendarEvent::query()->with($this->collaboration->eventRelations());
        $this->companyScope->apply($query, $user);
    
        // ── VISIBILITY SCOPE ─────────────────────────────────────────────
        // Only users with 'collaboration.manage' can see ALL company events.
        // Everyone else (including those with 'collaboration.view') only
        // sees events they organised or were invited to.
        // ─────────────────────────────────────────────────────────────────
        if (! $user->hasPermission('collaboration.manage')) {
            $query->where(fn (Builder $visible) => $visible
                ->where('organizer_user_id', $user->id)
                ->orWhereHas('attendeeRecords', fn (Builder $attendee) =>
                    $attendee->where('user_id', $user->id)
                )
            );
        }
    
        if ($type = (string) ($filters['event_type'] ?? '')) {
            $types = match ($type) {
                'appointment' => ['appointment', 'site_visit', 'inspection'],
                'meeting'     => ['meeting', 'interview'],
                'follow_up'   => ['follow_up', 'payment_follow_up'],
                'internal'    => ['internal', 'training'],
                default       => [$type],
            };
            $query->whereIn('event_type', $types);
        }
    
        return $query
            ->when($filters['status'] ?? null,
                fn (Builder $q, string $value) => $q->where('status', $value))
            ->when($filters['project_id'] ?? null,
                fn (Builder $q, int|string $value) => $q->where('project_id', $value))
            ->when($filters['priority'] ?? null,
                fn (Builder $q, string $value) => $q->where('metadata->priority', $value))
            ->when($filters['invitation_response'] ?? null,
                fn (Builder $q, string $value) => $q->whereHas('attendeeRecords',
                    fn (Builder $a) => $a->where('response', $value)))
            ->when($filters['q'] ?? null, function (Builder $q, string $value): void {
                $search = '%'.trim($value).'%';
                $q->where(fn (Builder $nested) => $nested
                    ->where('title', 'like', $search)
                    ->orWhere('event_number', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhere('location', 'like', $search)
                    ->orWhereHas('project', fn (Builder $project) =>
                        $project->where('name', 'like', $search)
                                ->orWhere('code', 'like', $search))
                    ->orWhereHas('organizer', fn (Builder $organizer) =>
                        $organizer->where('name', 'like', $search)
                                ->orWhere('email', 'like', $search))
                    ->orWhereHas('attendeeRecords', fn (Builder $attendee) =>
                        $attendee->where('name', 'like', $search)
                                ->orWhere('email', 'like', $search))
                );
            });
    }

    /** @param array<string,mixed> $filters */
    private function applyParticipantScope(Builder $query, User $user, array $filters): void
    {
        $participantId = (int) ($filters['participant_user_id'] ?? 0);
        if ($participantId > 0) {
            $this->whereParticipant($query, [$participantId]);
            return;
        }
        $scope = (string) ($filters['scope'] ?? 'all');
        if ($scope === 'mine') {
            $this->whereParticipant($query, [$user->id]);
        } elseif ($scope === 'team') {
            $department = Employee::query()->where('user_id', $user->id)->value('department');
            $ids = $department
                ? Employee::query()->where('company_id', $user->company_id)->where('department', $department)->whereNotNull('user_id')->pluck('user_id')->map(fn ($id) => (int) $id)->all()
                : [$user->id];
            $this->whereParticipant($query, $ids);
        }
    }

    /** @param array<int,int> $userIds */
    private function whereParticipant(Builder $query, array $userIds): void
    {
        $query->where(fn (Builder $participant) => $participant
            ->whereIn('organizer_user_id', $userIds)
            ->orWhereHas('attendeeRecords', fn (Builder $attendee) => $attendee->whereIn('user_id', $userIds)));
    }

    /** @param array<string,mixed> $filters @return array{CarbonImmutable,CarbonImmutable} */
    private function period(string $view, CarbonImmutable $focus, array $filters): array
    {
        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            return [
                CarbonImmutable::parse($filters['date_from'] ?? $focus, $focus->timezone)->startOfDay(),
                CarbonImmutable::parse($filters['date_to'] ?? $focus, $focus->timezone)->endOfDay(),
            ];
        }
        return match ($view) {
            'day' => [$focus->startOfDay(), $focus->endOfDay()],
            'week', 'employee', 'team' => [$focus->startOfWeek(CarbonImmutable::SUNDAY), $focus->endOfWeek(CarbonImmutable::SATURDAY)],
            default => [$focus->startOfMonth()->startOfWeek(CarbonImmutable::SUNDAY), $focus->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY)],
        };
    }

    /** @return array<string,int> */
    private function summary(Builder $query, string $timezone): array
    {
        $now = CarbonImmutable::now($timezone);
        $todayStart = $now->startOfDay()->utc();
        $todayEnd = $now->endOfDay()->utc();
        $open = ['scheduled', 'rescheduled'];

        return [
            'total' => (clone $query)->count(),
            'today' => (clone $query)->where('starts_at', '<=', $todayEnd)->where('ends_at', '>=', $todayStart)->count(),
            'upcoming' => (clone $query)->whereIn('status', $open)->where('starts_at', '>', $now->utc())->count(),
            'pending' => (clone $query)->whereIn('status', $open)->whereIn('event_type', ['follow_up', 'payment_follow_up'])->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'missed' => (clone $query)->whereIn('status', $open)->where('ends_at', '<', $now->utc())->where('metadata->attendance_status', 'missed')->count(),
            'overdue' => (clone $query)->whereIn('status', $open)->where('ends_at', '<', $now->utc())->count(),
        ];
    }

    private function applySummaryFilter(Builder $query, string $summary, string $timezone): void
    {
        $now = CarbonImmutable::now($timezone);
        $open = ['scheduled', 'rescheduled'];
        match ($summary) {
            'today' => $query->where('starts_at', '<=', $now->endOfDay()->utc())->where('ends_at', '>=', $now->startOfDay()->utc()),
            'upcoming' => $query->whereIn('status', $open)->where('starts_at', '>', $now->utc()),
            'pending' => $query->whereIn('status', $open)->whereIn('event_type', ['follow_up', 'payment_follow_up']),
            'completed' => $query->where('status', 'completed'),
            'missed' => $query->whereIn('status', $open)->where('ends_at', '<', $now->utc())->where('metadata->attendance_status', 'missed'),
            'overdue' => $query->whereIn('status', $open)->where('ends_at', '<', $now->utc()),
            default => null,
        };
    }

    private function periodLabel(string $view, CarbonImmutable $focus): string
    {
        return match ($view) {
            'day' => $focus->format('D, d M Y'),
            'week', 'employee', 'team' => $focus->startOfWeek(CarbonImmutable::SUNDAY)->format('d M').' – '.$focus->endOfWeek(CarbonImmutable::SATURDAY)->format('d M Y'),
            default => $focus->format('M Y'),
        };
    }

    /** @return Collection<int,array<string,mixed>> */
    private function calendarDays(CarbonImmutable $start, CarbonImmutable $end, int $targetMonth, Collection $events, string $timezone): Collection
    {
        $days = collect();
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $dayStart = $day->startOfDay(); $dayEnd = $day->endOfDay();
            $days->push([
                'date' => $day, 'in_month' => $day->month === $targetMonth,
                'events' => $events->filter(function (CalendarEvent $event) use ($dayStart, $dayEnd, $timezone): bool {
                    $range = $this->presenter->localRange($event, $timezone);
                    return $range['start']->lte($dayEnd) && $range['end']->gte($dayStart);
                })->values(),
            ]);
        }
        return $days;
    }

    /** @return Collection<int,array<string,mixed>> */
    private function timedDays(string $view, CarbonImmutable $focus, CarbonImmutable $periodStart, Collection $events, string $timezone): Collection
    {
        $days = $view === 'day' ? collect([$focus]) : collect(range(0, 6))->map(fn (int $offset) => $periodStart->addDays($offset));
        return $days->map(function (CarbonImmutable $day) use ($events, $timezone): array {
            $items = $events->filter(function (CalendarEvent $event) use ($day, $timezone): bool {
                $range = $this->presenter->localRange($event, $timezone);
                return $range['start']->isSameDay($day) || ($range['start']->lt($day->startOfDay()) && $range['end']->gt($day->startOfDay()));
            })->sortBy('starts_at')->values();
            $columns = [];
            $blocks = $items->map(function (CalendarEvent $event) use (&$columns, $day, $timezone): array {
                $range = $this->presenter->localRange($event, $timezone);
                $start = $range['start']->max($day->startOfDay()->addHours(self::DAY_START_HOUR));
                $end = $range['end']->min($day->startOfDay()->addHours(self::DAY_END_HOUR + 1));
                $startMinute = max(0, self::DAY_START_HOUR * -60 + $start->hour * 60 + $start->minute);
                $endMinute = max($startMinute + 20, self::DAY_START_HOUR * -60 + $end->hour * 60 + $end->minute);
                $column = 0;
                while (isset($columns[$column]) && $columns[$column] > $startMinute) $column++;
                $columns[$column] = $endMinute;
                return [
                    'event' => $event, 'column' => $column,
                    'top' => round($startMinute / 60 * 52, 2),
                    'height' => max(26, round(($endMinute - $startMinute) / 60 * 52, 2)),
                    'color' => $this->presenter->color($event),
                    'local_start' => $range['start'], 'local_end' => $range['end'],
                ];
            });
            return ['date' => $day, 'blocks' => $blocks, 'columns' => max(1, count($columns))];
        });
    }

    /** @return Collection<int,array<string,mixed>> */
    private function employeeLanes(User $user, Collection $events): Collection
    {
        $users = User::query()->with(['employee', 'role'])->where('status', 'active')
            ->when($user->company_id, fn (Builder $q, int $companyId) => $q->where('company_id', $companyId))
            ->orderBy('name')->get();
        return $users->map(fn (User $employee): array => [
            'label' => $employee->name,
            'sub' => $employee->employee?->designation ?? $employee->role?->name ?? 'Team member',
            'user' => $employee,
            'events' => $events->filter(fn (CalendarEvent $event) => (int) $event->organizer_user_id === (int) $employee->id || $event->attendeeRecords->contains('user_id', $employee->id))->values(),
        ]);
    }

    /** @return Collection<int,array<string,mixed>> */
    private function teamLanes(User $user, Collection $events): Collection
    {
        return $this->employeeLanes($user, $events)->groupBy(fn (array $lane) => $lane['user']->employee?->department ?: 'General')
            ->map(fn (Collection $members, string $department): array => [
                'label' => $department, 'sub' => $members->count().' member(s)',
                'events' => $members->flatMap(fn (array $member) => $member['events'])->unique('id')->sortBy('starts_at')->values(),
            ])->values();
    }

    /** @param array<string,mixed> $filters */
    private function selectedEvent(User $user, array $filters): ?CalendarEvent
    {
        $id = (int) ($filters['event_id'] ?? 0);
        if ($id < 1) return null;
        $event = CalendarEvent::with($this->collaboration->eventRelations())->findOrFail($id);
        abort_unless($user->can('view', $event), 403);
        return $event;
    }
}
