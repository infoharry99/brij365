<?php

namespace App\Domain\Scoring\Services;

use Illuminate\Validation\ValidationException;

final class ScoringRoundingPolicy
{
    /** @param array<string, mixed> $configuration */
    public function apply(float $value, array $configuration): float
    {
        $precision = (int) data_get($configuration, 'rounding.precision', 2);
        $factor = 10 ** $precision;

        return match (data_get($configuration, 'rounding.method', 'half_up')) {
            'floor' => floor($value * $factor) / $factor,
            'ceil' => ceil($value * $factor) / $factor,
            'half_even' => round($value, $precision, PHP_ROUND_HALF_EVEN),
            default => round($value, $precision, PHP_ROUND_HALF_UP),
        };
    }

    /** @param array<string, mixed> $configuration */
    public function normalizedToRange(
        float $normalizedScore,
        float $minimum,
        float $maximum,
        array $configuration,
    ): float {
        if ($maximum <= $minimum) {
            throw ValidationException::withMessages([
                'configuration.rating_scale' => 'Rating scale maximum must be greater than its minimum.',
            ]);
        }

        $normalizedScore = min(100.0, max(0.0, $normalizedScore));
        $mapped = $minimum + (($normalizedScore / 100) * ($maximum - $minimum));

        return $this->apply($mapped, $configuration);
    }
}
