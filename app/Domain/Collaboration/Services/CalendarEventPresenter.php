<?php

namespace App\Domain\Collaboration\Services;

use App\Models\CalendarEvent;
use Carbon\CarbonImmutable;

final class CalendarEventPresenter
{
    /** @var array<string, string> */
    private const LEGACY_TYPES = [
        'site_visit' => 'appointment',
        'interview' => 'meeting',
        'payment_follow_up' => 'follow_up',
        'inspection' => 'appointment',
        'training' => 'internal',
    ];

    /** @return array<string, string> */
    public function types(): array
    {
        return [
            'meeting' => 'Meeting', 'call' => 'Call', 'follow_up' => 'Follow-up',
            'demo' => 'Demo', 'appointment' => 'Appointment', 'task_deadline' => 'Task Deadline',
            'internal' => 'Internal', 'client_event' => 'Client Event', 'reminder' => 'Reminder',
        ];
    }

    public function type(CalendarEvent|string $event): string
    {
        $value = $event instanceof CalendarEvent ? $event->event_type : $event;

        return self::LEGACY_TYPES[$value] ?? $value;
    }

    public function label(CalendarEvent|string $event): string
    {
        return $this->types()[$this->type($event)] ?? str($this->type($event))->headline()->toString();
    }

    public function color(CalendarEvent|string $event): string
    {
        return [
            'meeting' => '#F6740C', 'call' => '#0ea5a4', 'follow_up' => '#e08600',
            'demo' => '#7c3aed', 'appointment' => '#2570eb', 'task_deadline' => '#e22d3d',
            'internal' => '#64748b', 'client_event' => '#12a85b', 'reminder' => '#db2777',
        ][$this->type($event)] ?? '#64748b';
    }

    /** @return array{start:CarbonImmutable,end:CarbonImmutable,duration:int} */
    public function localRange(CalendarEvent $event, string $timezone): array
    {
        $start = CarbonImmutable::instance($event->starts_at)->setTimezone($timezone);
        $end = CarbonImmutable::instance($event->ends_at)->setTimezone($timezone);

        return ['start' => $start, 'end' => $end, 'duration' => max(1, $start->diffInMinutes($end))];
    }
}
