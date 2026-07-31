<?php

namespace App\Application\Scoring\Actions;

use App\Application\Scoring\DTOs\ScoringOverviewPageData;
use App\Application\Scoring\DTOs\LogicCenterSectionData;
use App\Domain\Scoring\Services\LogicCenterAccessService;
use App\Domain\Scoring\Services\LogicCenterRegister;
use App\Domain\Scoring\Services\PerformanceSimulationRuleRegister;
use App\Domain\Scoring\Services\RosterSimulationRuleRegister;
use App\Domain\Scoring\Services\ScoringRuleRegister;
use App\Domain\Scoring\Services\ScoringRuleCatalog;
use App\Domain\Payroll\Services\StatutoryRulePackDefinitionValidator;
use App\Models\ScoringRule;
use App\Models\User;

final class ShowScoringOverview
{
    private const LABELS = [
        'overview' => ['Logic Center Overview', 'Governed formulas, statutory packs, roster variables, simulations and immutable audit history.'],
        'business' => ['Business Scoring', 'Versioned lead, recruitment, vendor, project, customer and employee decision rules.'],
        'lead' => ['Lead Scoring', 'Qualification criteria, bands and routing for sales leads.'],
        'performance' => ['Employee Performance', 'Weighted employee performance and rating rules.'],
        'confirmation' => ['Employee Confirmation', 'Confirmation recommendation criteria and outcome bands.'],
        'recruitment' => ['Recruitment Interview', 'Competency weights, panel requirements and recommendation bands.'],
        'vendor' => ['Vendor Performance', 'Delivery, quality, commercial and compliance scorecards.'],
        'project' => ['Project Health', 'Construction, sales, collections, cost and schedule health factors.'],
        'customer-satisfaction' => ['Customer Satisfaction', 'Valid sample rules, penalties and CSAT bands.'],
        'exit-feedback' => ['Exit Feedback', 'Organizational feedback trends and risk indicators.'],
        'score-history' => ['Score History', 'Calculated and overridden score snapshots by rule version.'],
        'rule-history' => ['Rule Change History', 'Rule versions, checksums, creators and lifecycle status.'],
        'statutory' => ['Statutory & Payroll Rules', 'Effective-dated central and work-location state packs with verified official-source evidence.'],
        'roster' => ['Attendance & Roster Rules', 'Governed shift, rotation, swap, attendance finalization and reopen variables.'],
        'simulation' => ['Simulation & Impact', 'Non-mutating performance, statutory payroll and roster impact checks.'],
        'audit' => ['Versions, Recalculation & Audit', 'Version checksums, activation history, recalculation activity and failure evidence.'],
    ];

    private const SECTIONS = [
        'overview' => ['Overview', 'Cross-domain readiness and governed changes.', 'fa-gauge-high'],
        'business' => ['Business Scoring', 'Lead, recruitment, vendor, project and customer rules.', 'fa-chart-line'],
        'performance' => ['Employee Performance', 'Criteria, rating bands and controlled overrides.', 'fa-user-check'],
        'statutory' => ['Statutory & Payroll Rules', 'Verified central and state formula packs.', 'fa-scale-balanced'],
        'roster' => ['Attendance & Roster Rules', 'Rotations, swaps, locks and payable-day inputs.', 'fa-calendar-days'],
        'simulation' => ['Simulation & Impact', 'Read-only impact calculations before activation.', 'fa-flask'],
        'audit' => ['Versions, Recalculation & Audit', 'Recalculation, checksums and immutable evidence.', 'fa-clock-rotate-left'],
    ];

    public function __construct(
        private readonly ScoringRuleRegister $register,
        private readonly ScoringRuleCatalog $catalog,
        private readonly LogicCenterAccessService $access,
        private readonly LogicCenterRegister $logicCenter,
        private readonly PerformanceSimulationRuleRegister $performanceSimulations,
        private readonly RosterSimulationRuleRegister $rosterSimulations,
    )
    {
    }

    /** @param array<string, mixed> $filters */
    public function handle(User $user, array $filters): ScoringOverviewPageData
    {
        $view = (string) ($filters['view'] ?? 'overview');
        [$title, $description] = self::LABELS[$view] ?? self::LABELS['overview'];
        $data = $this->register->forUser($user, $filters);
        $ruleTypes = $this->ruleTypesForView($user, $view);
        $sections = collect(self::SECTIONS)
            ->filter(fn (array $definition, string $key): bool => $this->access->canViewSection($user, $key))
            ->map(fn (array $definition, string $key): LogicCenterSectionData => new LogicCenterSectionData(
                key: $key,
                label: $definition[0],
                description: $definition[1],
                icon: $definition[2],
                url: route('scoring.index', $key === 'overview' ? [] : ['view' => $key]),
                active: $this->sectionForView($view) === $key,
            ))->values()->all();

        return new ScoringOverviewPageData(
            view: $view,
            title: $title,
            description: $description,
            counts: $data['counts'],
            rules: $data['rules'],
            snapshots: $data['snapshots'],
            recalculationRuns: $data['runs'],
            recalculationFailures: $data['failures'],
            ruleTypes: $ruleTypes,
            statutoryPackTypes: collect(StatutoryRulePackDefinitionValidator::GOVERNED_SETTING_KEYS)
                ->mapWithKeys(fn (string $key): array => [$key => str($key)->replace('.', ' ')->headline()->toString()])
                ->all(),
            canCreate: $ruleTypes !== [],
            sections: $sections,
            variablePacks: $this->logicCenter->variablePacks($user, $view),
            readiness: $this->logicCenter->readiness($user),
            capabilities: $this->access->capabilities($user),
            performanceSimulationRules: $view === 'simulation'
                ? $this->performanceSimulations->forUser($user)
                : [],
            rosterSimulationRules: $view === 'simulation'
                ? $this->rosterSimulations->forUser($user)
                : [],
        );
    }

    private function sectionForView(string $view): string
    {
        return match ($view) {
            'lead', 'confirmation', 'recruitment', 'vendor', 'project', 'customer-satisfaction', 'exit-feedback' => 'business',
            'score-history', 'rule-history' => 'audit',
            default => $view,
        };
    }

    /** @return array<string, string> */
    private function ruleTypesForView(User $user, string $view): array
    {
        $labels = $this->catalog->labels();
        $selectedKey = match ($view) {
            'performance' => 'employee_performance',
            'lead' => 'lead_quality',
            'confirmation' => 'employee_confirmation',
            'recruitment' => 'recruitment_interview',
            'vendor' => 'vendor_performance',
            'project' => 'project_health',
            'customer-satisfaction' => 'customer_satisfaction',
            'exit-feedback' => 'exit_feedback',
            default => null,
        };

        if ($selectedKey !== null) {
            $labels = isset($labels[$selectedKey]) ? [$selectedKey => $labels[$selectedKey]] : [];
        }

        if ($selectedKey === null && $view === 'business') {
            unset($labels['employee_performance']);
        }

        return collect($labels)
            ->filter(fn (string $label, string $ruleKey): bool => $user->can(
                'createForKey',
                [ScoringRule::class, $ruleKey],
            ))
            ->all();
    }
}
