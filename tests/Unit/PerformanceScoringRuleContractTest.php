<?php

namespace Tests\Unit;

use App\Domain\Scoring\Services\PerformanceScoringSourceRegistry;
use App\Domain\Scoring\Services\ScoringConfigurationValidator;
use App\Domain\Scoring\Services\ScoringRoundingPolicy;
use App\Domain\Scoring\Services\ScoringRuleCatalog;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PerformanceScoringRuleContractTest extends TestCase
{
    public function test_normalized_scores_map_across_the_complete_non_zero_rating_scale(): void
    {
        $policy = app(ScoringRoundingPolicy::class);
        $configuration = ['rounding' => ['method' => 'half_up', 'precision' => 2]];

        $this->assertSame(1.0, $policy->normalizedToRange(0, 1, 5, $configuration));
        $this->assertSame(3.0, $policy->normalizedToRange(50, 1, 5, $configuration));
        $this->assertSame(5.0, $policy->normalizedToRange(100, 1, 5, $configuration));
    }

    #[DataProvider('roundingCases')]
    public function test_configured_rounding_method_and_precision_are_applied(
        string $method,
        int $precision,
        float $value,
        float $expected,
    ): void {
        $policy = app(ScoringRoundingPolicy::class);

        $this->assertSame($expected, $policy->apply($value, [
            'rounding' => ['method' => $method, 'precision' => $precision],
        ]));
    }

    /** @return iterable<string, array{string, int, float, float}> */
    public static function roundingCases(): iterable
    {
        yield 'half up' => ['half_up', 2, 2.345, 2.35];
        yield 'half even' => ['half_even', 2, 2.345, 2.34];
        yield 'floor' => ['floor', 2, 2.349, 2.34];
        yield 'ceil' => ['ceil', 2, 2.341, 2.35];
        yield 'three decimal precision' => ['half_up', 3, 1.23456, 1.235];
    }

    public function test_employee_performance_rule_rejects_an_unsupported_source(): void
    {
        $configuration = app(ScoringRuleCatalog::class)->defaultConfiguration('employee_performance');
        $configuration['criteria'][0]['source'] = 'unregistered_performance_input';

        try {
            app(ScoringConfigurationValidator::class)->validateForRule('employee_performance', $configuration);
            $this->fail('An unsupported Employee Performance source must fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('configuration.criteria.0.source', $exception->errors());
        }
    }

    public function test_registry_contains_exactly_the_implemented_performance_sources(): void
    {
        $this->assertSame([
            'kpi_achievement',
            'kra_achievement',
            'competencies',
            'behaviour',
            'attendance',
            'self_review',
            'manager_review',
            'hr_calibration',
        ], app(PerformanceScoringSourceRegistry::class)->keys());
    }
}
