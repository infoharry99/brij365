<?php

namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\CalendarWorkspaceData;
use App\Domain\Collaboration\Services\CalendarEventRegister;
use App\Domain\Collaboration\Services\CollaborationWorkspaceOptions;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Domain\Collaboration\Services\CalendarWorkspaceRegister;
use App\Domain\Collaboration\Services\CalendarEventPresenter;

final class ListCalendarWorkspace
{
    public function __construct(
        private readonly CalendarEventRegister $events,
        private readonly CollaborationWorkspaceOptions $options,
        private readonly CalendarWorkspaceRegister $workspace,
        private readonly CalendarEventPresenter $presenter,
    ) {}

    /** @param array<string,mixed> $filters */
    public function execute(User $user, array $filters): CalendarWorkspaceData
    {
        $workspace = $this->workspace->workspace($user, $filters);

        return new CalendarWorkspaceData(
            events: $workspace['events'],
            filters: $filters,
            companies: $this->options->companies($user),
            projects: $this->options->projects($user),
            eventTypes: $this->presenter->types(),
            statuses: $this->options->eventStatuses(),
            canCreate: $user->can('create', CalendarEvent::class),
            canManage: $user->hasPermission('collaboration.manage'),
            users: $this->options->internalUsers($user),
            periodEvents: $workspace['period_events'],
            summary: $workspace['summary'],
            selectedEvent: $workspace['selected_event'],
            focusDate: $workspace['focus_date'],
            periodStart: $workspace['period_start'],
            periodEnd: $workspace['period_end'],
            periodLabel: $workspace['period_label'],
            calendarDays: $workspace['calendar_days'],
            hours: $workspace['hours'],
            employeeLanes: $workspace['employee_lanes'],
            teamLanes: $workspace['team_lanes'],
            timedDays: $workspace['timed_days'],
            timezone: $workspace['timezone'],
        );
    }
}
