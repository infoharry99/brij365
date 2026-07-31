<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;
use App\Services\Collaboration\CollaborationService;
use App\Services\Operations\HealthCheckService;
use App\Services\Operations\SqliteBackupService;
use App\Jobs\SynchronizeMailboxAccountJob;
use App\Models\MailboxAccount;
use App\Jobs\SendScheduledMailboxMessageJob;
use App\Models\MailboxOutboxMessage;
use App\Domain\Collaboration\Services\TaskReminderDispatcher;
use App\Domain\Collaboration\Services\CalendarReminderDispatcher;
use App\Domain\Collaboration\Services\CalendarRecurrenceGenerator;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('builder360:database-prepare {--path= : Optional SQLite database file path}', function (): int {
    if (config('database.default') === 'sqlite') {
        return Artisan::call('builder360:sqlite-prepare', array_filter([
            '--path' => $this->option('path') ?: null,
        ]));
    }

    $this->comment('Default database connection is '.config('database.default').'. No local database file preparation required.');

    return self::SUCCESS;
})->purpose('Prepare local database prerequisites for the configured connection');

Artisan::command('builder360:sqlite-prepare {--path= : Optional SQLite database file path}', function (): int {
    if (config('database.default') !== 'sqlite') {
        $this->comment('Default database connection is not sqlite. No SQLite file preparation required.');

        return self::SUCCESS;
    }

    $path = (string) ($this->option('path') ?: config('database.connections.sqlite.database'));

    if ($path === ':memory:') {
        $this->comment('SQLite in-memory database selected. No file preparation required.');

        return self::SUCCESS;
    }

    $resolvedPath = match (true) {
        str_starts_with($path, '/'),
        str_starts_with($path, '\\'),
        preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 => $path,
        default => base_path($path),
    };

    $directory = dirname($resolvedPath);

    if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
        $this->error('Unable to create SQLite database directory: '.$directory);

        return self::FAILURE;
    }

    if (! file_exists($resolvedPath) && touch($resolvedPath) === false) {
        $this->error('Unable to create SQLite database file: '.$resolvedPath);

        return self::FAILURE;
    }

    $this->info('SQLite database file is ready: '.$resolvedPath);

    return self::SUCCESS;
})->purpose('Create the configured SQLite database file before migrations run');

Artisan::command('builder360:sqlite-backup {--output-dir= : Relative private storage backup directory} {--retention-days= : Retention window in days} {--json : Output machine-readable JSON}', function (SqliteBackupService $backupService): int {
    try {
        $retentionDays = $this->option('retention-days');
        $payload = $backupService->backup(
            $this->option('output-dir') ?: null,
            $retentionDays === null || $retentionDays === '' ? null : (int) $retentionDays,
        );

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('SQLite backup completed.');
        $this->line('Backup file: '.$payload['backup_file']);
        $this->line('Manifest file: '.$payload['manifest_file']);
        $this->line('Size: '.$payload['size_bytes'].' bytes');
        $this->line('SHA-256: '.$payload['checksum_sha256']);
        $this->line('Retention deleted: '.$payload['retention_deleted']);

        return self::SUCCESS;
    } catch (Throwable $exception) {
        $payload = [
            'status' => 'failed',
            'error' => class_basename($exception),
            'message' => $exception->getMessage(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->error($payload['message']);
        }

        return self::FAILURE;
    }
})->purpose('Create a private, checksummed SQLite database backup with manifest and retention pruning');

Artisan::command('builder360:sqlite-backup-verify {manifest? : Relative private storage manifest path} {--json : Output machine-readable JSON}', function (SqliteBackupService $backupService): int {
    try {
        $payload = $backupService->verify($this->argument('manifest') ?: null);

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $payload['status'] === 'ok' ? self::SUCCESS : self::FAILURE;
        }

        if ($payload['status'] === 'ok') {
            $this->info('SQLite backup verification passed.');
        } else {
            $this->error('SQLite backup verification failed: '.($payload['failure'] ?? 'unknown'));
        }

        $this->line('Manifest file: '.($payload['manifest_file'] ?? 'not found'));
        $this->line('Backup file: '.($payload['backup_file'] ?? 'not found'));
        $this->line('Checksum valid: '.(($payload['checksum_matches_manifest'] ?? false) ? 'yes' : 'no'));
        $this->line('Integrity check: '.(($payload['integrity_check']['result'] ?? null) ?: 'not available'));

        return $payload['status'] === 'ok' ? self::SUCCESS : self::FAILURE;
    } catch (Throwable $exception) {
        $payload = [
            'status' => 'failed',
            'error' => class_basename($exception),
            'message' => $exception->getMessage(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->error($payload['message']);
        }

        return self::FAILURE;
    }
})->purpose('Verify a private SQLite backup manifest, checksum and SQLite integrity without restoring it');

Artisan::command('builder360:verify {--json : Output machine-readable JSON}', function (): int {
    $health = app(HealthCheckService::class)->readiness();
    $checks = $health['checks'] ?? [];
    $routeCount = Route::getRoutes()->count();
    $classicAssetsReady = ($checks['assets']['status'] ?? null) === 'ok';
    $appKeyConfigured = filled(config('app.key'));

    $verification = [
        'status' => 'ok',
        'application' => [
            'name' => config('app.name'),
            'environment' => config('app.env'),
            'debug' => (bool) config('app.debug'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ],
        'database' => [
            'connection' => config('database.default'),
            'driver' => $checks['database']['driver'] ?? null,
            'status' => $checks['database']['status'] ?? 'unknown',
        ],
        'readiness' => [
            'status' => $health['status'] ?? 'unknown',
            'checks' => collect($checks)
                ->map(fn (array $check): string => (string) ($check['status'] ?? 'unknown'))
                ->all(),
        ],
        'artifacts' => [
            'classic_assets' => $classicAssetsReady ? 'present' : 'missing',
            'route_count' => $routeCount,
        ],
        'security' => [
            'app_key_configured' => $appKeyConfigured,
            'debug_disabled' => config('app.debug') === false,
        ],
    ];

    $failures = collect([
        'readiness' => ($health['status'] ?? null) === 'ok',
        'classic_assets' => $classicAssetsReady,
        'routes_registered' => $routeCount > 0,
        'app_key_configured' => $appKeyConfigured,
    ])->reject(fn (bool $passed): bool => $passed)->keys()->values()->all();

    $verification['failures'] = $failures;
    $verification['status'] = $failures === [] ? 'ok' : 'degraded';

    if ($this->option('json')) {
        $this->line(json_encode($verification, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $verification['status'] === 'ok' ? self::SUCCESS : self::FAILURE;
    }

    $this->info('Builder360 verification status: '.$verification['status']);
    $this->line('Environment: '.$verification['application']['environment']);
    $this->line('Database: '.$verification['database']['connection'].' / '.$verification['database']['status']);
    $this->line('Readiness: '.$verification['readiness']['status']);
    $this->line('Classic assets: '.$verification['artifacts']['classic_assets']);
    $this->line('Registered routes: '.$verification['artifacts']['route_count']);

    if ($failures !== []) {
        $this->warn('Failures: '.implode(', ', $failures));

        return self::FAILURE;
    }

    return self::SUCCESS;
})->purpose('Run a safe Builder360 deployment verification summary');

Artisan::command('collaboration:release-scheduled-messages {--json : Output machine-readable JSON}', function (CollaborationService $service): int {
    $released = $service->releaseDueScheduledMessages(now());
    $payload = [
        'status' => 'ok',
        'released' => $released,
        'released_at' => now()->toISOString(),
    ];

    if ($this->option('json')) {
        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    $this->info("Released {$released} scheduled mailbox message(s).");

    return self::SUCCESS;
})->purpose('Release due scheduled collaboration mailbox messages and notify recipients');

Artisan::command('collaboration:dispatch-task-reminders {--json : Output machine-readable JSON}', function (TaskReminderDispatcher $dispatcher): int {
    $sent = $dispatcher->dispatchDue(now());
    $payload = ['status' => 'ok', 'notifications_sent' => $sent, 'processed_at' => now()->toISOString()];
    $this->option('json') ? $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) : $this->info("Sent {$sent} task reminder notification(s).");

    return self::SUCCESS;
})->purpose('Send idempotent due and overdue task reminders');

Artisan::command('collaboration:generate-recurring-tasks {--json : Output machine-readable JSON}', function (\App\Domain\Collaboration\Services\TaskRecurrenceService $service): int {
    $generated = $service->generateDue(now());
    $payload = ['status' => 'ok', 'tasks_generated' => $generated, 'processed_at' => now()->toISOString()];
    $this->option('json') ? $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) : $this->info("Generated {$generated} recurring task(s).");

    return self::SUCCESS;
})->purpose('Generate due recurring tasks exactly once');

Artisan::command('collaboration:dispatch-calendar-reminders {--json}', function (CalendarReminderDispatcher $dispatcher): int {
    $queued = $dispatcher->dispatchDue(now());
    $this->option('json') ? $this->line(json_encode(['status'=>'ok','reminders_queued'=>$queued])) : $this->info("Queued {$queued} calendar reminder(s).");
    return self::SUCCESS;
})->purpose('Queue due Calendar reminders exactly once');

Artisan::command('collaboration:generate-calendar-occurrences {--json}', function (CalendarRecurrenceGenerator $generator): int {
    $generated = $generator->generateDue(now());
    $this->option('json') ? $this->line(json_encode(['status'=>'ok','occurrences_generated'=>$generated])) : $this->info("Generated {$generated} Calendar occurrence(s).");
    return self::SUCCESS;
})->purpose('Generate due recurring Calendar occurrences exactly once');

Artisan::command('mailbox:sync {--json : Output machine-readable JSON}', function (\App\Application\Mailbox\Actions\SynchronizeMailboxAccount $action): int {
    $accounts = MailboxAccount::query()->where('status', '!=', 'disabled')->where('sync_enabled', true)->get();
    $results = [];
    foreach ($accounts as $account) {
        try {
            $run = $action->execute($account);
            $results[] = ['account' => $account->email, 'status' => 'ok', 'created' => $run->messages_created, 'updated' => $run->messages_updated];
        } catch (Throwable $e) {
            $results[] = ['account' => $account->email, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }
    if ($this->option('json')) {
        $this->line(json_encode(['status' => 'ok', 'syncs' => $results], JSON_PRETTY_PRINT));
    } else {
        $this->info("Mailbox sync completed for " . count($accounts) . " account(s).");
    }
    return self::SUCCESS;
})->purpose('Synchronize all active IMAP mailbox accounts');

if ((bool) config('builder360.scheduler.enabled', true)) {
    $schedulerOutputPath = (string) config('builder360.scheduler.output_path', storage_path('logs/builder360-scheduler.log'));
    $schedulerTimezone = (string) config('builder360.scheduler.timezone', config('app.timezone', 'UTC'));

    Schedule::command('collaboration:release-scheduled-messages --json')
        ->description('Release due scheduled collaboration mailbox messages')
        ->everyFiveMinutes()
        ->timezone($schedulerTimezone)
        ->withoutOverlapping(10)
        ->appendOutputTo($schedulerOutputPath);

    Schedule::command('collaboration:dispatch-task-reminders --json')
        ->description('Dispatch due and overdue task reminders')
        ->everyFiveMinutes()
        ->timezone($schedulerTimezone)
        ->withoutOverlapping(10)
        ->appendOutputTo($schedulerOutputPath);

    Schedule::command('collaboration:generate-recurring-tasks --json')
        ->description('Generate due recurring tasks')
        ->everyFiveMinutes()
        ->timezone($schedulerTimezone)
        ->withoutOverlapping(10)
        ->appendOutputTo($schedulerOutputPath);

    Schedule::command('collaboration:dispatch-calendar-reminders --json')->everyMinute()->timezone($schedulerTimezone)->withoutOverlapping(10)->appendOutputTo($schedulerOutputPath);
    Schedule::command('collaboration:generate-calendar-occurrences --json')->everyFiveMinutes()->timezone($schedulerTimezone)->withoutOverlapping(10)->appendOutputTo($schedulerOutputPath);

    Schedule::call(function (\App\Application\Mailbox\Actions\SynchronizeMailboxAccount $action): void {
        MailboxAccount::query()->where('status', '!=', 'disabled')->where('sync_enabled', true)->get()
            ->each(function (MailboxAccount $account) use ($action): void {
                try {
                    $action->execute($account);
                } catch (Throwable $e) {
                    // Handled inside SynchronizeMailboxAccount
                }
            });
    })->name('mailbox:dispatch-account-syncs')->everyMinute()->timezone($schedulerTimezone)->withoutOverlapping(10);

    Schedule::call(function (): void {
        MailboxOutboxMessage::query()->where('state','scheduled')->where('scheduled_for','<=',now())->pluck('id')->each(fn(int $id)=>SendScheduledMailboxMessageJob::dispatch($id));
    })->name('mailbox:dispatch-scheduled-email')->everyMinute()->timezone($schedulerTimezone)->withoutOverlapping(10);

    Schedule::command('scoring:activate-scheduled --json')
        ->description('Activate due scoring rule versions and start recalculation')
        ->everyMinute()
        ->timezone($schedulerTimezone)
        ->withoutOverlapping(10)
        ->appendOutputTo($schedulerOutputPath);

    if (config('database.default') === 'sqlite') {
        Schedule::command('builder360:sqlite-backup --json')
            ->description('Builder360 SQLite backup')
            ->dailyAt((string) config('builder360.scheduler.sqlite_backup_at', '01:00'))
            ->timezone($schedulerTimezone)
            ->withoutOverlapping(120)
            ->appendOutputTo($schedulerOutputPath);

        Schedule::command('builder360:sqlite-backup-verify --json')
            ->description('Builder360 SQLite backup verification')
            ->dailyAt((string) config('builder360.scheduler.sqlite_backup_verify_at', '01:30'))
            ->timezone($schedulerTimezone)
            ->withoutOverlapping(120)
            ->appendOutputTo($schedulerOutputPath);
    }
}
