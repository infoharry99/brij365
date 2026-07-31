<?php

namespace App\Services\Crm;

use App\Domain\Scoring\Services\ActiveScoringRuleResolver;
use App\Models\ScoringRule;
use App\Models\Lead;
use App\Services\Settings\SystemSettingResolver;
use Illuminate\Validation\ValidationException;

class LeadQualityScoreService
{
    public const SETTING_KEY = 'crm.lead_quality_score.rules';

    public function __construct(
        private readonly SystemSettingResolver $settings,
        private readonly ActiveScoringRuleResolver $activeRules,
    ) {}

    /**
     * @param array<string, mixed> $data
     * @return array{
     *     score:int,
     *     raw_score:int,
     *     max_score:int,
     *     band:array<string, mixed>,
     *     components:array<string, int>,
     *     labels:array<string, string>,
     *     selected_conditions:array<string, string|null>,
     *     rules:array<string, mixed>
     * }
     */
    public function calculate(Lead $lead, array $data): array
    {
        $rules = $this->rulesForCompany((int) $lead->company_id);
        $criteria = $rules['criteria'];
        $conditions = is_array($data['quality_conditions'] ?? null) ? $data['quality_conditions'] : [];

        $components = [];
        $labels = [];
        $selectedConditions = [];
        $rawScore = 0;
        $maxScore = 0;

        foreach ($criteria as $criterionKey => $criterion) {
            $scoreField = $criterionKey.'_score';
            $maxPoints = (int) $criterion['max_points'];
            $maxScore += $maxPoints;
            $labels[$criterionKey] = (string) $criterion['label'];

            $conditionValue = isset($conditions[$criterionKey]) ? (string) $conditions[$criterionKey] : null;
            $selectedConditions[$criterionKey] = $conditionValue;

            if ($conditionValue !== null && $conditionValue !== '') {
                $points = $this->pointsForCondition($criterionKey, $criterion, $conditionValue);
            } elseif (array_key_exists($scoreField, $data) && $data[$scoreField] !== null && $data[$scoreField] !== '') {
                $points = (int) $data[$scoreField];
            } else {
                throw ValidationException::withMessages([
                    $scoreField => 'Enter a score or select a configured quality condition.',
                ]);
            }

            if ($points < 0 || $points > $maxPoints) {
                throw ValidationException::withMessages([
                    $scoreField => "{$labels[$criterionKey]} must be between 0 and {$maxPoints} for the active quality-score rule.",
                ]);
            }

            $components[$criterionKey] = $points;
            $rawScore += $points;
        }

        $normalizedScore = $maxScore > 0 ? (int) round(($rawScore / $maxScore) * 100) : 0;

        return [
            'score' => max(0, min(100, $normalizedScore)),
            'raw_score' => $rawScore,
            'max_score' => $maxScore,
            'band' => $this->bandForScore($normalizedScore, $rules['bands']),
            'components' => $components,
            'labels' => $labels,
            'selected_conditions' => $selectedConditions,
            'rules' => [
                'setting_key' => $rules['setting_key'] ?? null,
                'rule_key' => 'lead_quality',
                'source' => $rules['source'],
                'version' => $rules['version'],
                'criteria' => $criteria,
                'bands' => $rules['bands'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rulesForCompany(?int $companyId): array
    {
        if ($companyId !== null && ($rule = $this->activeRules->resolve($companyId, 'lead_quality')) !== null) {
            return $this->rulesFromScoringRule($rule);
        }

        $setting = $this->settings->active($companyId, self::SETTING_KEY);
        $value = is_array($setting?->value) ? $setting->value : [];
        $rules = $this->normalizeRules($value);

        $rules['source'] = $setting ? 'system_setting' : 'application_default';
        $rules['version'] = $setting?->version;
        $rules['setting_id'] = $setting?->id;
        $rules['setting_key'] = self::SETTING_KEY;

        return $rules;
    }

    /** @return array<string, mixed> */
    private function rulesFromScoringRule(ScoringRule $rule): array
    {
        $aliases = [
            'budget_fit' => 'budget',
            'decision_authority' => 'authority',
            'requirement_clarity' => 'need',
            'purchase_timeline' => 'timeline',
        ];
        $criteria = collect($rule->configuration['criteria'] ?? [])->mapWithKeys(
            static function (array $criterion) use ($aliases): array {
                $key = (string) ($criterion['key'] ?? '');
                $formKey = $aliases[$key] ?? $key;

                return [$formKey => [
                    'scoring_key' => $key,
                    'label' => (string) ($criterion['label'] ?? str($key)->headline()),
                    'max_points' => (int) ($criterion['max_points'] ?? 0),
                    'options' => collect($criterion['conditions'] ?? [])->map(static fn (array $condition): array => [
                        'value' => (string) ($condition['value'] ?? ''),
                        'label' => (string) ($condition['label'] ?? $condition['value'] ?? ''),
                        'points' => (int) ($condition['points'] ?? 0),
                    ])->values()->all(),
                ]];
            },
        )->all();
        $bands = collect($rule->configuration['bands'] ?? [])->map(static fn (array $band): array => [
            'label' => (string) ($band['label'] ?? 'Score Band'),
            'min_score' => (int) ($band['min_score'] ?? 0),
            'status_hint' => (string) ($band['outcome'] ?? 'nurture'),
            'tone' => match ($band['key'] ?? '') {
                'excellent', 'hot' => 'green', 'good', 'warm' => 'blue', 'attention', 'cold' => 'orange', default => 'red',
            },
        ])->all();

        return [
            'criteria' => $criteria,
            'bands' => $bands,
            'source' => 'scoring_rule',
            'version' => $rule->version,
            'rule_id' => $rule->id,
            'setting_id' => null,
            'setting_key' => null,
        ];
    }

    /**
     * @param array<string, mixed> $criterion
     */
    private function pointsForCondition(string $criterionKey, array $criterion, string $conditionValue): int
    {
        foreach (($criterion['options'] ?? []) as $option) {
            if (($option['value'] ?? null) === $conditionValue) {
                return (int) ($option['points'] ?? 0);
            }
        }

        throw ValidationException::withMessages([
            'quality_conditions.'.$criterionKey => 'The selected quality condition is not configured in the active scoring rule.',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $bands
     * @return array<string, mixed>
     */
    private function bandForScore(int $score, array $bands): array
    {
        $sortedBands = collect($bands)
            ->sortByDesc(fn (array $band): int => (int) ($band['min_score'] ?? 0))
            ->values();

        foreach ($sortedBands as $band) {
            if ($score >= (int) ($band['min_score'] ?? 0)) {
                return $band;
            }
        }

        return ['label' => 'Unclassified', 'min_score' => 0, 'status_hint' => 'nurture'];
    }

    /**
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function normalizeRules(array $value): array
    {
        $defaults = $this->defaultRules();
        $criteria = is_array($value['criteria'] ?? null) ? $value['criteria'] : $defaults['criteria'];
        $bands = is_array($value['bands'] ?? null) ? $value['bands'] : $defaults['bands'];

        $normalizedCriteria = [];
        foreach ($criteria as $key => $criterion) {
            if (! is_array($criterion)) {
                continue;
            }

            $maxPoints = (int) ($criterion['max_points'] ?? 0);
            if ($maxPoints <= 0 || $maxPoints > 100) {
                continue;
            }

            $options = [];
            foreach (($criterion['options'] ?? []) as $option) {
                if (! is_array($option) || ! isset($option['value'])) {
                    continue;
                }

                $points = (int) ($option['points'] ?? 0);
                if ($points < 0 || $points > $maxPoints) {
                    continue;
                }

                $options[] = [
                    'value' => (string) $option['value'],
                    'label' => (string) ($option['label'] ?? $option['value']),
                    'points' => $points,
                ];
            }

            $normalizedCriteria[(string) $key] = [
                'label' => (string) ($criterion['label'] ?? str((string) $key)->replace('_', ' ')->title()),
                'max_points' => $maxPoints,
                'options' => $options,
            ];
        }

        if ($normalizedCriteria === []) {
            $normalizedCriteria = $defaults['criteria'];
        }

        $normalizedBands = [];
        foreach ($bands as $band) {
            if (! is_array($band)) {
                continue;
            }

            $normalizedBands[] = [
                'label' => (string) ($band['label'] ?? 'Score Band'),
                'min_score' => max(0, min(100, (int) ($band['min_score'] ?? 0))),
                'status_hint' => (string) ($band['status_hint'] ?? 'nurture'),
                'tone' => (string) ($band['tone'] ?? 'slate'),
            ];
        }

        if ($normalizedBands === []) {
            $normalizedBands = $defaults['bands'];
        }

        return [
            'criteria' => $normalizedCriteria,
            'bands' => $normalizedBands,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultRules(): array
    {
        return [
            'criteria' => [
                'budget' => [
                    'label' => 'Budget Fit',
                    'max_points' => 25,
                    'options' => [
                        ['value' => 'unverified', 'label' => 'Budget not verified', 'points' => 0],
                        ['value' => 'below_range', 'label' => 'Below project range', 'points' => 8],
                        ['value' => 'near_range', 'label' => 'Near project range', 'points' => 16],
                        ['value' => 'confirmed_fit', 'label' => 'Confirmed budget fit', 'points' => 25],
                    ],
                ],
                'authority' => [
                    'label' => 'Decision Authority',
                    'max_points' => 25,
                    'options' => [
                        ['value' => 'unknown', 'label' => 'Authority unknown', 'points' => 0],
                        ['value' => 'influencer', 'label' => 'Influencer only', 'points' => 10],
                        ['value' => 'joint_decision', 'label' => 'Joint decision maker', 'points' => 18],
                        ['value' => 'decision_maker', 'label' => 'Primary decision maker', 'points' => 25],
                    ],
                ],
                'need' => [
                    'label' => 'Requirement Clarity',
                    'max_points' => 25,
                    'options' => [
                        ['value' => 'vague', 'label' => 'Requirement vague', 'points' => 5],
                        ['value' => 'configuration_known', 'label' => 'Configuration known', 'points' => 14],
                        ['value' => 'project_unit_fit', 'label' => 'Project/unit fit identified', 'points' => 21],
                        ['value' => 'urgent_specific', 'label' => 'Urgent and specific need', 'points' => 25],
                    ],
                ],
                'timeline' => [
                    'label' => 'Purchase Timeline',
                    'max_points' => 25,
                    'options' => [
                        ['value' => 'future', 'label' => 'Beyond 6 months', 'points' => 5],
                        ['value' => 'within_6_months', 'label' => 'Within 6 months', 'points' => 14],
                        ['value' => 'within_90_days', 'label' => 'Within 90 days', 'points' => 21],
                        ['value' => 'immediate', 'label' => 'Immediate / site visit ready', 'points' => 25],
                    ],
                ],
            ],
            'bands' => [
                ['label' => 'Hot Lead', 'min_score' => 75, 'status_hint' => 'qualified', 'tone' => 'green'],
                ['label' => 'Warm Lead', 'min_score' => 50, 'status_hint' => 'nurture', 'tone' => 'orange'],
                ['label' => 'Cold Lead', 'min_score' => 25, 'status_hint' => 'nurture', 'tone' => 'slate'],
                ['label' => 'Disqualified Fit', 'min_score' => 0, 'status_hint' => 'disqualified', 'tone' => 'red'],
            ],
        ];
    }
}
