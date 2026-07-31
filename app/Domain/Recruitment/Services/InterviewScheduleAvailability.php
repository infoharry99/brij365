<?php

namespace App\Domain\Recruitment\Services;

use App\Domain\Recruitment\Data\InterviewScheduleConflictData;
use App\Models\CalendarEvent;
use App\Models\Candidate;
use App\Models\Interview;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class InterviewScheduleAvailability
{
    private const ACTIVE_INTERVIEW_STATUSES = ['scheduled', 'rescheduled'];

    private const ACTIVE_CALENDAR_STATUSES = ['scheduled', 'rescheduled'];

    private const MAX_INTERVIEW_DURATION_MINUTES = 480;

    /**
     * @param array<int, int|string> $panelUserIds
     */
    public function inspect(
        Candidate $candidate,
        array $panelUserIds,
        CarbonInterface $startsAt,
        int $durationMinutes,
    ): InterviewScheduleConflictData {
        $candidate->loadMissing('jobOpening:id,project_id');

        $start = CarbonImmutable::instance($startsAt);
        $end = $start->addMinutes($durationMinutes);
        $projectId = $candidate->jobOpening?->project_id;
        $panelIds = collect($panelUserIds)
            ->map(static fn (int|string $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $activeInterviews = Interview::query()
            ->where('company_id', $candidate->company_id)
            ->whereIn('status', self::ACTIVE_INTERVIEW_STATUSES)
            ->where('scheduled_at', '<', $end)
            ->where('scheduled_at', '>', $start->subMinutes(self::MAX_INTERVIEW_DURATION_MINUTES))
            ->with('candidate.jobOpening:id,project_id')
            ->get()
            ->filter(function (Interview $interview) use ($start, $end, $projectId): bool {
                if ($projectId !== null && (int) $interview->candidate?->jobOpening?->project_id !== (int) $projectId) {
                    return false;
                }

                $interviewStart = CarbonImmutable::instance($interview->scheduled_at);
                $interviewEnd = $interviewStart->addMinutes((int) $interview->duration_minutes);

                return $interviewStart->lt($end) && $interviewEnd->gt($start);
            });

        $candidateConflict = $activeInterviews->contains(
            static fn (Interview $interview): bool => (int) $interview->candidate_id === (int) $candidate->id,
        );

        $panelInterviewConflict = $panelIds->isNotEmpty() && $activeInterviews->contains(
            static fn (Interview $interview): bool => $panelIds->intersect($interview->panel_user_ids ?? [])->isNotEmpty(),
        );

        $panelCalendarConflict = $panelIds->isNotEmpty()
            && $this->calendarEvents($candidate, $projectId, $start, $end)->contains(
                fn (CalendarEvent $event): bool => $this->calendarParticipantIds($event)->intersect($panelIds)->isNotEmpty(),
            );

        return new InterviewScheduleConflictData(
            candidateInterviewConflict: $candidateConflict,
            panelInterviewConflict: $panelInterviewConflict,
            panelCalendarConflict: $panelCalendarConflict,
        );
    }

    /**
     * @param array<int, int|string> $panelUserIds
     * @throws ValidationException
     */
    public function assertAvailable(
        Candidate $candidate,
        array $panelUserIds,
        CarbonInterface $startsAt,
        int $durationMinutes,
    ): void {
        $conflicts = $this->inspect($candidate, $panelUserIds, $startsAt, $durationMinutes);

        if ($conflicts->hasConflicts()) {
            throw ValidationException::withMessages($conflicts->validationMessages());
        }
    }

    private function calendarEvents(
        Candidate $candidate,
        ?int $projectId,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
    ): Collection {
        return CalendarEvent::query()
            ->where('company_id', $candidate->company_id)
            ->whereIn('status', self::ACTIVE_CALENDAR_STATUSES)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when($projectId !== null, fn ($query) => $query->where(
                fn ($project) => $project->whereNull('project_id')->orWhere('project_id', $projectId),
            ))
            ->with('attendeeRecords:id,calendar_event_id,user_id,response')
            ->get();
    }

    /** @return Collection<int, int> */
    private function calendarParticipantIds(CalendarEvent $event): Collection
    {
        $recordIds = $event->attendeeRecords
            ->reject(static fn ($attendee): bool => $attendee->response === 'declined')
            ->pluck('user_id');

        $legacyIds = collect($event->attendees ?? [])
            ->reject(static fn (array $attendee): bool => ($attendee['response'] ?? 'pending') === 'declined')
            ->pluck('user_id');

        return $recordIds
            ->merge($legacyIds)
            ->push($event->organizer_user_id)
            ->filter()
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }
}
