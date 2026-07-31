<?php

namespace App\Domain\Scoring\Services;

final class ScoringRuleConfigurationBuilder
{
    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function fromValidatedInput(array $validated): array
    {
        $criteria = collect($validated['criteria'])->reject(
            static fn (array $criterion): bool => (bool) ($criterion['remove'] ?? false),
        )->map(static function (array $criterion): array {
            $conditions = collect($criterion['conditions'] ?? [])->reject(
                static fn (array $condition): bool => (bool) ($condition['remove'] ?? false)
                    || trim((string) ($condition['key'] ?? '')) === '',
            )->map(static fn (array $condition): array => [
                'key' => trim((string) $condition['key']),
                'label' => trim((string) ($condition['label'] ?? '')),
                'operator' => (string) ($condition['operator'] ?? 'equals'),
                'value' => trim((string) ($condition['value'] ?? '')),
                'points' => (float) ($condition['points'] ?? 0),
            ])->values()->all();

            return [
                'key' => trim((string) $criterion['key']),
                'label' => trim((string) $criterion['label']),
                'weight' => (float) $criterion['weight'],
                'max_points' => (float) $criterion['max_points'],
                'source' => trim((string) $criterion['source']),
                'normalization' => (string) $criterion['normalization'],
                'input_scale' => [
                    'min' => (float) data_get($criterion, 'input_scale.min'),
                    'max' => (float) data_get($criterion, 'input_scale.max'),
                ],
                'required' => (bool) $criterion['required'],
                'missing_data_behavior' => (string) $criterion['missing_data_behavior'],
                'conditions' => $conditions,
            ];
        })->values()->all();

        $bands = collect($validated['bands'])->reject(
            static fn (array $band): bool => (bool) ($band['remove'] ?? false),
        )->map(static fn (array $band): array => [
            'key' => trim((string) $band['key']),
            'label' => trim((string) $band['label']),
            'min_score' => (int) $band['min_score'],
            'outcome' => trim((string) $band['outcome']),
        ])->sortByDesc('min_score')->values()->all();

        return [
            'criteria' => $criteria,
            'bands' => $bands,
            'rating_scale' => ['min' => (int) $validated['rating_min'], 'max' => (int) $validated['rating_max']],
            'thresholds' => ['passing_score' => (float) $validated['passing_score'], 'pip_score' => (float) $validated['pip_score']],
            'rounding' => ['method' => $validated['rounding_method'], 'precision' => (int) $validated['rounding_precision']],
            'minimum_sample_size' => (int) $validated['minimum_sample_size'],
            'override' => [
                'allowed' => (bool) $validated['override_allowed'],
                'reason_required' => (bool) $validated['override_reason_required'],
            ],
            'mandatory_conditions' => [],
        ];
    }
}
