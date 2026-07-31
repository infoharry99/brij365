<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class OperationalSchedulerTest extends TestCase
{
    public function test_sqlite_backup_and_verification_commands_are_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events());

        $backupEvent = $events->first(
            fn (mixed $event): bool => str_contains((string) ($event->command ?? ''), 'builder360:sqlite-backup --json')
        );
        $verifyEvent = $events->first(
            fn (mixed $event): bool => str_contains((string) ($event->command ?? ''), 'builder360:sqlite-backup-verify --json')
        );

        $this->assertNotNull($backupEvent);
        $this->assertNotNull($verifyEvent);
        $this->assertSame('0 1 * * *', $backupEvent->expression);
        $this->assertSame('30 1 * * *', $verifyEvent->expression);
        $this->assertTrue($backupEvent->withoutOverlapping);
        $this->assertTrue($verifyEvent->withoutOverlapping);
        $this->assertStringEndsWith('builder360-scheduler.log', (string) $backupEvent->output);
        $this->assertStringEndsWith('builder360-scheduler.log', (string) $verifyEvent->output);
    }
}
