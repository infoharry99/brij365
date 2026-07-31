<?php

namespace Tests\Feature;

use App\Domain\Scoring\Services\ScoringRuleCatalog;
use App\Domain\Scoring\Services\ScoringConfigurationChecksum;
use App\Models\PerformanceReview;
use App\Models\ScoreSnapshot;
use App\Models\ScoringRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PerformanceScoringSimulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_hr_user_can_simulate_a_version_without_mutating_review_evidence(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $rule = $this->performanceRule($user);
        $scores = $this->scoresFor($rule, 80.0);
        $reviewCount = PerformanceReview::query()->count();
        $snapshotCount = ScoreSnapshot::query()->count();

        $this->actingAs($user)
            ->post(route('scoring.performance-simulations.store', $rule), [
                'performance_simulation_rule_id' => $rule->id,
                'criterion_scores' => $scores,
            ])
            ->assertRedirect(route('scoring.index', ['view' => 'simulation']))
            ->assertSessionHas('status')
            ->assertSessionHas('performance_simulation', function (array $result) use ($rule): bool {
                return $result['rule_id'] === $rule->id
                    && $result['rule_version'] === 1
                    && $result['total_score'] === '80.00'
                    && $result['band_label'] === 'Excellent'
                    && $result['passing'] === true
                    && $result['pip_recommended'] === false
                    && $result['mutated_records'] === 0;
            });

        $this->assertSame($reviewCount, PerformanceReview::query()->count());
        $this->assertSame($snapshotCount, ScoreSnapshot::query()->count());
        $this->assertSame(1, ScoringRule::query()->whereKey($rule->id)->count());
    }

    public function test_same_rule_version_and_inputs_produce_the_same_hashes(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $rule = $this->performanceRule($user);
        $payload = [
            'performance_simulation_rule_id' => $rule->id,
            'criterion_scores' => $this->scoresFor($rule, 72.5),
        ];

        $first = $this->actingAs($user)->post(route('scoring.performance-simulations.store', $rule), $payload);
        $firstResult = $first->getSession()->get('performance_simulation');
        $second = $this->actingAs($user)->post(route('scoring.performance-simulations.store', $rule), $payload);
        $secondResult = $second->getSession()->get('performance_simulation');

        $this->assertSame($firstResult['input_hash'], $secondResult['input_hash']);
        $this->assertSame($firstResult['result_hash'], $secondResult['result_hash']);
        $this->assertSame('72.50', $secondResult['total_score']);
    }

    public function test_required_and_out_of_range_criterion_inputs_are_rejected(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $rule = $this->performanceRule($user);
        $scores = $this->scoresFor($rule, 75.0);
        unset($scores['kpi_achievement']);
        $scores['kra_achievement'] = 101;

        $this->from(route('scoring.index', ['view' => 'simulation']))
            ->actingAs($user)
            ->post(route('scoring.performance-simulations.store', $rule), [
                'performance_simulation_rule_id' => $rule->id,
                'criterion_scores' => $scores,
            ])
            ->assertRedirect(route('scoring.index', ['view' => 'simulation']))
            ->assertSessionHasErrors([
                'criterion_scores.kpi_achievement',
                'criterion_scores.kra_achievement',
            ])
            ->assertSessionMissing('performance_simulation');
    }

    public function test_unknown_criterion_is_rejected(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $rule = $this->performanceRule($user);

        $this->from(route('scoring.index', ['view' => 'simulation']))
            ->actingAs($user)
            ->post(route('scoring.performance-simulations.store', $rule), [
                'performance_simulation_rule_id' => $rule->id,
                'criterion_scores' => $this->scoresFor($rule, 70.0) + ['unconfigured_metric' => 99],
            ])
            ->assertRedirect(route('scoring.index', ['view' => 'simulation']))
            ->assertSessionHasErrors('criterion_scores.unconfigured_metric');
    }

    public function test_payroll_only_user_cannot_simulate_performance_rules_by_direct_request(): void
    {
        $this->seed();
        $hr = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $payroll = User::query()->where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $rule = $this->performanceRule($hr);

        $this->actingAs($payroll)
            ->post(route('scoring.performance-simulations.store', $rule), [
                'performance_simulation_rule_id' => $rule->id,
                'criterion_scores' => $this->scoresFor($rule, 70.0),
            ])
            ->assertForbidden();
    }

    public function test_simulation_page_renders_the_versioned_performance_form_and_safety_guard(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $rule = $this->performanceRule($user);

        $this->actingAs($user)
            ->get(route('scoring.index', ['view' => 'simulation']))
            ->assertOk()
            ->assertSee('Performance score simulation')
            ->assertSee($rule->name)
            ->assertSee('criterion_scores[kpi_achievement]', false)
            ->assertSee('cannot create or update a performance review');
    }

    private function performanceRule(User $creator): ScoringRule
    {
        $configuration = app(ScoringRuleCatalog::class)->defaultConfiguration('employee_performance');

        return ScoringRule::query()->create([
            'company_id' => $creator->company_id,
            'created_by_user_id' => $creator->id,
            'rule_key' => 'employee_performance',
            'name' => 'Versioned performance simulation fixture',
            'version' => 1,
            'status' => 'active',
            'configuration' => $configuration,
            'configuration_checksum' => app(ScoringConfigurationChecksum::class)->make($configuration),
            'change_reason' => 'Verify non-mutating performance simulation behavior.',
            'activated_at' => now(),
        ]);
    }

    /** @return array<string, float> */
    private function scoresFor(ScoringRule $rule, float $score): array
    {
        return collect((array) data_get($rule->configuration, 'criteria', []))
            ->mapWithKeys(static fn (array $criterion): array => [(string) $criterion['key'] => $score])
            ->all();
    }
}
