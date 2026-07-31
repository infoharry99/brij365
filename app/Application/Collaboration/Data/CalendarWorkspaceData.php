<?php

namespace App\Application\Collaboration\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use App\Models\CalendarEvent;
use Carbon\CarbonImmutable;

final readonly class CalendarWorkspaceData
{
    /** @param LengthAwarePaginator<int, \App\Models\CalendarEvent> $events @param array<string,mixed> $filters */
    public function __construct(
        public LengthAwarePaginator $events,
        public array $filters,
        public Collection $companies,
        public Collection $projects,
        public array $eventTypes,
        public array $statuses,
        public bool $canCreate,
        public bool $canManage,
        public Collection $users,
        public Collection $periodEvents,
        public array $summary,
        public ?CalendarEvent $selectedEvent,
        public CarbonImmutable $focusDate,
        public CarbonImmutable $periodStart,
        public CarbonImmutable $periodEnd,
        public string $periodLabel,
        public Collection $calendarDays,
        public array $hours,
        public Collection $employeeLanes,
        public Collection $teamLanes,
        public Collection $timedDays,
        public string $timezone,
    ) {}
}
