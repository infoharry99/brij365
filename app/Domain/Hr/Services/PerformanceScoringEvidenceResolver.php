<?php

namespace App\Domain\Hr\Services;

use App\Models\PerformanceReview;
use App\Models\ScoreSnapshot;
use App\Models\ScoringRule;
use Illuminate\Validation\ValidationException;

final class PerformanceScoringEvidenceResolver
{
    /**
     * Return content-free references to the evidence that produced a score.
     * The hashes detect stale review evidence without copying comments or other
     * sensitive review content into snapshot metadata.
     *
     * @return array{version: int, hash: string, references: array<string, array<string, mixed>>}
     */
    public function snapshot(PerformanceReview $review, ScoringRule $rule): array
    {
        $inputs = $review->scoring_inputs ?? [];
        $references = collect((array) data_get($rule->configuration, 'criteria', []))
            ->filter(static fn (mixed $criterion): bool => is_array($criterion))
            ->mapWithKeys(function (array $criterion) use ($review, $inputs): array {
                $key = (string) ($criterion['key'] ?? '');
                $source = (string) ($criterion['source'] ?? $key);

                if ($key === '' || $source === '') {
                    return [];
                }

                $supporting = collect($this->supportingFields($source))
                    ->map(fn (string $field): array => [
                        'path' => 'performance_reviews.'.$field,
                        'present' => $this->present(data_get($review, $field)),
                        'value_hash' => $this->valueHash(data_get($review, $field)),
                    ])->values()->all();

                return [$key => [
                    'source' => $source,
                    'source_path' => 'performance_reviews.scoring_inputs.'.$source,
                    'source_present' => array_key_exists($source, $inputs) && $inputs[$source] !== null,
                    'source_value_hash' => $this->valueHash($inputs[$source] ?? null),
                    'supporting_fields' => $supporting,
                ]];
            })->all();

        return [
            'version' => 1,
            'hash' => hash('sha256', json_encode($this->canonicalize($references), JSON_THROW_ON_ERROR)),
            'references' => $references,
        ];
    }

    public function assertCurrent(PerformanceReview $review, ScoringRule $rule, ScoreSnapshot $snapshot): void
    {
        $expected = data_get($snapshot->metadata, 'performance_evidence.hash');
        if (is_string($expected) && $expected !== '') {
            $actual = $this->snapshot($review, $rule)['hash'];
            if (! hash_equals($expected, $actual)) {
                throw ValidationException::withMessages([
                    'score_snapshot' => 'Performance evidence changed after the governed score was calculated. Recalibrate before closing this review.',
                ]);
            }

            return;
        }

        // Existing snapshots created before evidence-reference version 1 still
        // retain the scoring-input hash. Preserve their safe closure path while
        // requiring all newly calculated snapshots to use the richer trace.
        $legacyExpected = data_get($snapshot->metadata, 'expected_scoring_inputs_hash');
        if (is_string($legacyExpected) && $legacyExpected !== '') {
            $actual = hash('sha256', json_encode($review->scoring_inputs ?? [], JSON_THROW_ON_ERROR));
            if (! hash_equals($legacyExpected, $actual)) {
                throw ValidationException::withMessages([
                    'score_snapshot' => 'Performance inputs changed after the governed score was calculated. Recalibrate before closing this review.',
                ]);
            }

            return;
        }

        throw ValidationException::withMessages([
            'score_snapshot' => 'The governed score snapshot has no verifiable performance evidence. Recalibrate before closing this review.',
        ]);
    }

    /** @return list<string> */
    private function supportingFields(string $source): array
    {
        return match ($source) {
            'kpi_achievement' => ['kpis'],
            'kra_achievement' => ['kra_summary'],
            'competencies', 'behaviour', 'manager_review' => ['manager_comments'],
            'self_review' => ['strengths', 'improvement_areas'],
            'hr_calibration' => ['hr_comments'],
            'attendance' => ['scoring_inputs.attendance_evidence'],
            default => [],
        };
    }

    private function present(mixed $value): bool
    {
        return match (true) {
            is_string($value) => trim($value) !== '',
            is_array($value) => $value !== [],
            default => $value !== null,
        };
    }

    private function valueHash(mixed $value): string
    {
        return hash('sha256', json_encode($this->canonicalize($value), JSON_THROW_ON_ERROR));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
    }
}
