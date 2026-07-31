<?php

namespace App\Domain\Governance\Services;

use App\Models\ReportPin;
use App\Models\ReportSchedule;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ReportSubscriptionManager
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function pin(User $actor, array $data, Request $request): ReportPin
    {
        return DB::transaction(function () use ($actor, $data, $request): ReportPin {
            $pin = ReportPin::updateOrCreate(['user_id' => $actor->id, 'report_key' => $data['report_key']], ['label' => $data['label'], 'filters' => $data['filters'] ?? []]);
            $this->audit->record($actor, 'governance.report.pinned', 'Pinned governance report', $pin, ['report_key' => $pin->report_key, 'label' => $pin->label], $request);
            return $pin;
        });
    }

    public function unpin(ReportPin $pin, User $actor, Request $request): void
    {
        DB::transaction(function () use ($pin, $actor, $request): void {
            $this->audit->record($actor, 'governance.report.unpinned', 'Unpinned governance report', $pin, ['report_key' => $pin->report_key, 'label' => $pin->label], $request);
            $pin->delete();
        });
    }

    public function schedule(User $actor, array $data, Request $request): ReportSchedule
    {
        return DB::transaction(function () use ($actor, $data, $request): ReportSchedule {
            $startsOn = Carbon::parse($data['starts_on'])->startOfDay();
            $schedule = ReportSchedule::create([
                'user_id' => $actor->id, 'company_id' => $actor->company_id, 'report_key' => $data['report_key'], 'label' => $data['label'],
                'frequency' => $data['frequency'], 'format' => $data['format'], 'filters' => $data['filters'] ?? [],
                'recipients' => collect($data['recipients'])->map(fn ($email) => strtolower(trim((string) $email)))->values()->all(),
                'starts_on' => $startsOn->toDateString(), 'ends_on' => $data['ends_on'] ?? null,
                'next_run_at' => $this->nextRunAt($startsOn, $data['frequency']), 'status' => 'active',
            ]);
            $this->audit->record($actor, 'governance.report_schedule.created', 'Created governance report schedule', $schedule, ['report_key' => $schedule->report_key, 'frequency' => $schedule->frequency, 'format' => $schedule->format, 'recipient_count' => count($schedule->recipients ?? []), 'next_run_at' => $schedule->next_run_at?->toISOString()], $request);
            return $schedule;
        });
    }

    public function archive(ReportSchedule $schedule, User $actor, Request $request): ReportSchedule
    {
        return DB::transaction(function () use ($schedule, $actor, $request): ReportSchedule {
            $schedule->forceFill(['status' => 'archived'])->save();
            $this->audit->record($actor, 'governance.report_schedule.archived', 'Archived governance report schedule', $schedule, ['report_key' => $schedule->report_key, 'frequency' => $schedule->frequency], $request);
            return $schedule->fresh();
        });
    }

    private function nextRunAt(Carbon $startsOn, string $frequency): Carbon
    {
        $candidate = $startsOn->copy()->setTime(8, 0);
        if ($candidate->isFuture()) return $candidate;
        return match ($frequency) {
            'daily' => now()->addDay()->setTime(8, 0),
            'weekly' => now()->addWeek()->startOfWeek()->setTime(8, 0),
            'monthly' => now()->addMonthNoOverflow()->startOfMonth()->setTime(8, 0),
            default => now()->addDay()->setTime(8, 0),
        };
    }
}
