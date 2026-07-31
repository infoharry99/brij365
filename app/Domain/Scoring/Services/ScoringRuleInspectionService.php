<?php

namespace App\Domain\Scoring\Services;

use App\Application\Scoring\DTOs\ScoringRuleInspectionPageData;
use App\Models\AuditEvent;
use App\Models\EmployeeConfirmationCase;
use App\Models\EmployeeExitInterview;
use App\Models\Interview;
use App\Models\Lead;
use App\Models\PerformanceReview;
use App\Models\Project;
use App\Models\ScoringRule;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Validation\ValidationException;

final class ScoringRuleInspectionService
{
    public function inspect(ScoringRule $rule, User $user, ?int $compareTo = null): ScoringRuleInspectionPageData
    {
        $rule->loadMissing('createdBy:id,name');
        $versions = ScoringRule::query()->where('company_id', $rule->company_id)->where('rule_key', $rule->rule_key)
            ->orderByDesc('version')->get(['id', 'version', 'status', 'configuration'])->all();
        $comparison = null;
        if ($compareTo !== null) {
            $comparison = collect($versions)->first(fn (ScoringRule $version): bool => $version->id === $compareTo);
            if (! $comparison) {
                throw ValidationException::withMessages(['compare_to' => 'Select another version of the same scoring rule.']);
            }
        }
        [$eligible, $preserved, $label] = $this->impact($rule);

        return new ScoringRuleInspectionPageData(
            id: $rule->id, name: $rule->name, ruleKey: $rule->rule_key, version: $rule->version,
            status: str($rule->status)->headline()->toString(), checksum: $rule->configuration_checksum,
            effectiveAt: $rule->effective_at?->format('d M Y, h:i A') ?? 'Not scheduled',
            changeReason: $rule->change_reason, createdBy: $rule->createdBy?->name ?? 'Unknown',
            versions: collect($versions)->map(fn (ScoringRule $version): array => ['id' => $version->id, 'version' => $version->version, 'status' => str($version->status)->headline()->toString()])->all(),
            criteria: $rule->configuration['criteria'] ?? [], bands: $rule->configuration['bands'] ?? [],
            differences: $comparison ? $this->differences($rule->configuration ?? [], $comparison->configuration ?? []) : [],
            ratingMin: (int) data_get($rule->configuration, 'rating_scale.min', 1),
            ratingMax: (int) data_get($rule->configuration, 'rating_scale.max', 5),
            comparedVersion: $comparison?->version, eligibleRecords: $eligible, preservedRecords: $preserved,
            impactLabel: $label, activity: $this->activity($rule),
        );
    }

    /** @return array{int, int, string} */
    private function impact(ScoringRule $rule): array
    {
        $company = $rule->company_id;
        return match ($rule->rule_key) {
            'lead_quality' => [Lead::where('company_id', $company)->whereNotIn('status', ['won', 'lost'])->count(), Lead::where('company_id', $company)->whereIn('status', ['won', 'lost'])->count(), 'Open leads eligible; won and lost leads preserved'],
            'employee_performance' => [PerformanceReview::where('company_id', $company)->whereNotIn('status', ['closed', 'cancelled'])->count(), PerformanceReview::where('company_id', $company)->whereIn('status', ['closed', 'cancelled'])->count(), 'Open reviews eligible; closed reviews preserved'],
            'employee_confirmation' => [EmployeeConfirmationCase::where('company_id', $company)->whereNull('hr_decided_at')->count(), EmployeeConfirmationCase::where('company_id', $company)->whereNotNull('hr_decided_at')->count(), 'Undecided cases eligible; HR decisions preserved'],
            'recruitment_interview' => [Interview::where('company_id', $company)->whereNotIn('status', ['completed', 'cancelled'])->count(), Interview::where('company_id', $company)->whereIn('status', ['completed', 'cancelled'])->count(), 'Open interviews eligible; completed decisions preserved'],
            'vendor_performance' => [Vendor::where('company_id', $company)->where('status', 'active')->count(), 0, 'Active vendor rolling scorecards eligible'],
            'project_health' => [Project::where('company_id', $company)->whereNotIn('status', ['completed', 'cancelled'])->count(), Project::where('company_id', $company)->whereIn('status', ['completed', 'cancelled'])->count(), 'Live projects eligible; closed projects preserved'],
            'customer_satisfaction' => [Project::where('company_id', $company)->whereHas('serviceTickets', fn ($query) => $query->whereNotNull('customer_rating'))->count(), 0, 'Project summaries eligible; individual ticket ratings unchanged'],
            'exit_feedback' => [EmployeeExitInterview::query()->join('employees', 'employees.id', '=', 'employee_exit_interviews.employee_id')
                ->where('employee_exit_interviews.company_id', $company)->whereNotNull('employee_exit_interviews.submitted_at')
                ->whereNotNull('employees.department')->distinct()->count('employees.department'), 0,
                'Department summaries eligible; individual responses unchanged'],
            default => [0, 0, 'No eligible source mapping is configured'],
        };
    }

    /** @param array<string, mixed> $current @param array<string, mixed> $other @return list<array<string, mixed>> */
    private function differences(array $current, array $other): array
    {
        $rows = [];
        foreach (['criteria', 'bands', 'rating_scale', 'thresholds', 'rounding', 'minimum_sample_size', 'override'] as $section) {
            $currentValue = $current[$section] ?? null;
            $otherValue = $other[$section] ?? null;
            if ($currentValue !== $otherValue) {
                $rows[] = ['section' => str($section)->headline()->toString(), 'current' => $currentValue, 'compared' => $otherValue];
            }
        }
        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function activity(ScoringRule $rule): array
    {
        return AuditEvent::query()->with('user:id,name')->where('auditable_type', ScoringRule::class)->where('auditable_id', $rule->id)
            ->latest()->limit(50)->get()->map(fn (AuditEvent $event): array => [
                'event' => str($event->event_type)->afterLast('.')->replace('_', ' ')->headline()->toString(),
                'actor' => $event->user?->name ?? 'System', 'at' => $event->created_at?->format('d M Y, h:i A') ?? 'Unavailable',
            ])->all();
    }
}
