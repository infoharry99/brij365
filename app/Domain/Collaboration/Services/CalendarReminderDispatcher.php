<?php

namespace App\Domain\Collaboration\Services;

use App\Jobs\SendCalendarReminderJob;
use App\Models\CalendarEventReminderDelivery;
use Carbon\CarbonInterface;

final class CalendarReminderDispatcher
{
    public function dispatchDue(CarbonInterface $now): int
    {
        $ids = CalendarEventReminderDelivery::query()->where('status', 'pending')->where('attempt_count', '<', 3)->where('scheduled_for', '<=', $now)->orderBy('id')->limit(500)->pluck('id');
        $ids->each(fn (int $id) => SendCalendarReminderJob::dispatch($id));
        return $ids->count();
    }
}
