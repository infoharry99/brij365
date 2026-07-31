<?php

namespace App\Domain\Scoring\Services;

use Illuminate\Validation\ValidationException;

final class ScoringRuleCatalog
{
    /** @return array<string, string> */
    public function labels(): array
    {
        return [
            'lead_quality' => 'Lead Scoring',
            'employee_performance' => 'Employee Performance',
            'employee_confirmation' => 'Employee Confirmation',
            'recruitment_interview' => 'Recruitment Interview',
            'vendor_performance' => 'Vendor Performance',
            'project_health' => 'Project Health',
            'customer_satisfaction' => 'Customer Satisfaction',
            'exit_feedback' => 'Exit Feedback',
        ];
    }

    /** @return array<string, mixed> */
    public function defaultConfiguration(string $ruleKey): array
    {
        $criteria = match ($ruleKey) {
            'lead_quality' => $this->leadQualityCriteria(),
            'employee_performance' => $this->weightedCriteria(['kpi_achievement' => 20, 'kra_achievement' => 15, 'competencies' => 15, 'behaviour' => 10, 'attendance' => 10, 'self_review' => 10, 'manager_review' => 10, 'hr_calibration' => 10]),
            'employee_confirmation' => $this->weightedCriteria(['performance' => 20, 'behaviour' => 15, 'attendance' => 15, 'culture_fit' => 15, 'training_completion' => 10, 'policy_compliance' => 10, 'manager_recommendation' => 15]),
            'recruitment_interview' => $this->weightedCriteria(['role_competency' => 30, 'technical_capability' => 25, 'communication' => 15, 'culture_fit' => 15, 'problem_solving' => 15]),
            'vendor_performance' => $this->weightedCriteria(['acceptance_rate' => 15, 'quality' => 15, 'on_time_delivery' => 15, 'fulfillment' => 10, 'price_competitiveness' => 10, 'documentation' => 10, 'responsiveness' => 10, 'issue_resolution' => 15]),
            'project_health' => $this->weightedCriteria(['construction_progress' => 15, 'sales_progress' => 10, 'collection_progress' => 10, 'budget_control' => 15, 'schedule_variance' => 10, 'inventory_health' => 10, 'approval_delays' => 10, 'procurement_delays' => 10, 'receivables' => 10]),
            'customer_satisfaction' => $this->weightedCriteria(['customer_rating' => 50, 'resolution_time' => 20, 'reopened_penalty' => 10, 'escalation_impact' => 10, 'valid_sample' => 10]),
            'exit_feedback' => $this->weightedCriteria(['overall_experience' => 20, 'manager_relationship' => 15, 'workload' => 10, 'compensation' => 15, 'career_growth' => 15, 'work_environment' => 15, 'rehire_recommendation' => 10]),
            default => throw ValidationException::withMessages(['rule_key' => 'The selected scoring area is not supported.']),
        };

        return [
            'criteria' => $criteria,
            'bands' => $ruleKey === 'lead_quality' ? [
                ['key' => 'hot', 'label' => 'Hot Lead', 'min_score' => 75, 'outcome' => 'qualified'],
                ['key' => 'warm', 'label' => 'Warm Lead', 'min_score' => 50, 'outcome' => 'nurture'],
                ['key' => 'cold', 'label' => 'Cold Lead', 'min_score' => 25, 'outcome' => 'nurture'],
                ['key' => 'disqualified', 'label' => 'Disqualified Fit', 'min_score' => 0, 'outcome' => 'disqualified'],
            ] : [
                ['key' => 'excellent', 'label' => 'Excellent', 'min_score' => 80, 'outcome' => 'positive'],
                ['key' => 'good', 'label' => 'Good', 'min_score' => 60, 'outcome' => 'review'],
                ['key' => 'attention', 'label' => 'Needs Attention', 'min_score' => 40, 'outcome' => 'attention'],
                ['key' => 'critical', 'label' => 'Critical', 'min_score' => 0, 'outcome' => 'escalate'],
            ],
            'rating_scale' => ['min' => 1, 'max' => 5],
            'thresholds' => ['passing_score' => 60, 'pip_score' => 40],
            'rounding' => ['method' => 'half_up', 'precision' => 2],
            'minimum_sample_size' => in_array($ruleKey, ['customer_satisfaction', 'exit_feedback'], true) ? 5 : 1,
            'override' => ['allowed' => true, 'reason_required' => true],
            'mandatory_conditions' => [],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function criteria(array $keys): array
    {
        $weight = (int) (100 / count($keys));

        return collect($keys)->map(fn (string $key): array => [
            'key' => $key,
            'label' => str($key)->replace('_', ' ')->title()->toString(),
            'weight' => $weight,
            'max_points' => 25,
            'conditions' => [],
        ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function leadQualityCriteria(): array
    {
        $options = [
            'budget_fit' => [
                ['unverified', 'Budget not verified', 0],
                ['below_range', 'Below project range', 8],
                ['near_range', 'Near project range', 16],
                ['confirmed_fit', 'Confirmed budget fit', 25],
            ],
            'decision_authority' => [
                ['unknown', 'Authority unknown', 0],
                ['influencer', 'Influencer only', 10],
                ['joint_decision', 'Joint decision maker', 18],
                ['decision_maker', 'Primary decision maker', 25],
            ],
            'requirement_clarity' => [
                ['vague', 'Requirement vague', 5],
                ['configuration_known', 'Configuration known', 14],
                ['project_unit_fit', 'Project or unit fit identified', 21],
                ['urgent_specific', 'Urgent and specific need', 25],
            ],
            'purchase_timeline' => [
                ['future', 'Beyond 6 months', 5],
                ['within_6_months', 'Within 6 months', 14],
                ['within_90_days', 'Within 90 days', 21],
                ['immediate', 'Immediate or site visit ready', 25],
            ],
        ];

        return collect($options)->map(function (array $conditions, string $key): array {
            return [
                'key' => $key,
                'label' => str($key)->replace('_', ' ')->title()->toString(),
                'weight' => 25,
                'max_points' => 25,
                'conditions' => collect($conditions)->map(fn (array $condition): array => [
                    'key' => $condition[0],
                    'label' => $condition[1],
                    'operator' => 'equals',
                    'value' => $condition[0],
                    'points' => $condition[2],
                ])->all(),
            ];
        })->values()->all();
    }

    /** @param array<string, int> $weights @return array<int, array<string, mixed>> */
    private function weightedCriteria(array $weights): array
    {
        return collect($weights)->map(fn (int $weight, string $key): array => [
            'key' => $key,
            'label' => str($key)->replace('_', ' ')->title()->toString(),
            'weight' => $weight,
            'max_points' => 100,
            'source' => $key,
            'normalization' => 'rating_scale',
            'input_scale' => ['min' => 0, 'max' => 5],
            'required' => true,
            'missing_data_behavior' => 'block',
            'conditions' => [],
        ])->values()->all();
    }
}
