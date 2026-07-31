<?php

namespace Tests\Feature;

use App\Application\Scoring\DTOs\CreateScoringRuleData;
use App\Domain\Scoring\Services\ActiveScoringRuleResolver;
use App\Domain\Scoring\Services\ScoringRuleDraftService;
use App\Domain\Scoring\Services\ScoringRuleLifecycleService;
use App\Domain\Scoring\Services\StructuredScoreCalculator;
use App\Models\AuditEvent;
use App\Models\ScoringRecalculationRun;
use App\Models\ScoringRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

final class ScoringRuleIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_tampered_rule_is_rejected_at_every_lifecycle_boundary_and_audited(): void
    {
        [$administrator, $approver] = $this->governanceActors();
        $lifecycle = app(ScoringRuleLifecycleService::class);

        $validateRule = $this->draft('lead_quality', $administrator);
        $this->tamper($validateRule);
        $this->assertIntegrityFailure(fn () => $lifecycle->validateRule($validateRule->fresh(), $administrator));
        $this->assertSame('draft', $validateRule->fresh()->status);

        $submitRule = $this->draft('employee_performance', $administrator);
        $lifecycle->validateRule($submitRule, $administrator);
        $this->tamper($submitRule);
        $this->assertIntegrityFailure(fn () => $lifecycle->submit($submitRule->fresh(), $administrator));
        $this->assertSame('validated', $submitRule->fresh()->status);

        $approveRule = $this->draft('employee_confirmation', $administrator);
        $lifecycle->validateRule($approveRule, $administrator);
        $lifecycle->submit($approveRule->fresh(), $administrator);
        $this->tamper($approveRule);
        $this->assertIntegrityFailure(fn () => $lifecycle->approve($approveRule->fresh(), $approver));
        $this->assertSame('pending_approval', $approveRule->fresh()->status);

        $scheduleAt = now()->addDays(10)->startOfMinute();
        $scheduleRule = $this->draft('vendor_performance', $administrator, $scheduleAt->format('Y-m-d H:i:s'));
        $this->approve($scheduleRule, $administrator, $approver);
        $this->tamper($scheduleRule);
        $this->assertIntegrityFailure(fn () => $lifecycle->activate($scheduleRule->fresh(), $approver));
        $this->assertSame('approved', $scheduleRule->fresh()->status);

        $activateAt = now()->addDays(11)->startOfMinute();
        $activateRule = $this->draft('project_health', $administrator, $activateAt->format('Y-m-d H:i:s'));
        $this->approve($activateRule, $administrator, $approver);
        $lifecycle->activate($activateRule->fresh(), $approver);
        $this->assertSame('scheduled', $activateRule->fresh()->status);
        $this->tamper($activateRule);
        $this->travelTo($activateAt->copy()->addMinute());
        $this->assertIntegrityFailure(fn () => $lifecycle->activate($activateRule->fresh(), $approver));
        $this->travelBack();
        $this->assertSame('scheduled', $activateRule->fresh()->status);

        $audits = AuditEvent::query()->where('event_type', 'scoring.rule.integrity_failed')->get();
        $this->assertCount(5, $audits);
        $this->assertSame([
            'scoring.rule.validated',
            'scoring.rule.submitted',
            'scoring.rule.approved',
            'scoring.rule.activate',
            'scoring.rule.activate',
        ], $audits->pluck('metadata.attempted_event')->all());
        $this->assertSame(0, ScoringRecalculationRun::query()->count());
    }

    public function test_active_resolver_and_calculator_fail_closed_after_warm_cache_tampering(): void
    {
        [$administrator, $approver] = $this->governanceActors();
        $rule = $this->draft('lead_quality', $administrator);
        $this->approve($rule, $administrator, $approver);
        app(ScoringRuleLifecycleService::class)->activate($rule->fresh(), $approver);

        $resolver = app(ActiveScoringRuleResolver::class);
        $this->assertSame($rule->id, $resolver->resolve($rule->company_id, $rule->rule_key)?->id);

        $this->tamper($rule);

        $this->assertIntegrityFailure(fn () => $resolver->resolve($rule->company_id, $rule->rule_key));
        $this->assertIntegrityFailure(fn () => app(StructuredScoreCalculator::class)->calculate($rule->fresh(), [
            'budget_fit' => 'confirmed_fit',
            'decision_authority' => 'decision_maker',
            'requirement_clarity' => 'urgent_specific',
            'purchase_timeline' => 'immediate',
        ]));
    }

    public function test_submitted_rule_business_content_is_immutable_while_lifecycle_can_advance(): void
    {
        [$administrator, $approver] = $this->governanceActors();
        $rule = $this->draft('customer_satisfaction', $administrator);
        app(ScoringRuleLifecycleService::class)->validateRule($rule, $administrator);
        app(ScoringRuleLifecycleService::class)->submit($rule->fresh(), $administrator);

        $mutations = [
            ['name' => 'Changed outside governance'],
            ['rule_key' => 'exit_feedback'],
            ['version' => 99],
            ['change_reason' => 'Attempt to rewrite governed evidence.'],
            ['configuration' => ['criteria' => []]],
            ['configuration_checksum' => str_repeat('a', 64)],
            ['effective_at' => now()->addMonth()],
            ['metadata' => ['source' => 'rewritten']],
        ];

        foreach ($mutations as $attributes) {
            $this->assertLogicFailure(function () use ($rule, $attributes): void {
                $rule->fresh()->forceFill($attributes)->save();
            });
        }

        $lifecycle = app(ScoringRuleLifecycleService::class);
        $lifecycle->approve($rule->fresh(), $approver);
        $lifecycle->activate($rule->fresh(), $approver);
        $this->assertSame('active', $rule->fresh()->status);

        $this->assertLogicFailure(fn () => $rule->fresh()->delete());
        $this->assertDatabaseHas('scoring_rules', ['id' => $rule->id, 'status' => 'active']);
    }

    public function test_same_family_cannot_claim_the_same_future_effective_slot_but_other_keys_can(): void
    {
        [$administrator, $approver] = $this->governanceActors();
        $lifecycle = app(ScoringRuleLifecycleService::class);
        $effectiveAt = now()->addDays(7)->startOfMinute();

        $first = $this->draft('project_health', $administrator, $effectiveAt->format('Y-m-d H:i:s'));
        $this->approve($first, $administrator, $approver);
        $lifecycle->activate($first->fresh(), $approver);
        $this->assertSame('scheduled', $first->fresh()->status);

        $collision = $this->draft('project_health', $administrator, $effectiveAt->format('Y-m-d H:i:s'));
        $this->approve($collision, $administrator, $approver);
        $this->assertValidationFailure('effective_at', fn () => $lifecycle->activate($collision->fresh(), $approver));
        $this->assertSame('approved', $collision->fresh()->status);

        $otherFamily = $this->draft('vendor_performance', $administrator, $effectiveAt->format('Y-m-d H:i:s'));
        $this->approve($otherFamily, $administrator, $approver);
        $lifecycle->activate($otherFamily->fresh(), $approver);
        $this->assertSame('scheduled', $otherFamily->fresh()->status);
    }

    public function test_newer_version_cannot_become_effective_before_an_earlier_governed_version(): void
    {
        [$administrator, $approver] = $this->governanceActors();
        $lifecycle = app(ScoringRuleLifecycleService::class);
        $laterAt = now()->addDays(14)->startOfMinute();
        $earlierAt = now()->addDays(7)->startOfMinute();

        $versionOne = $this->draft('exit_feedback', $administrator, $laterAt->format('Y-m-d H:i:s'));
        $this->approve($versionOne, $administrator, $approver);
        $lifecycle->activate($versionOne->fresh(), $approver);

        $versionTwo = $this->draft('exit_feedback', $administrator, $earlierAt->format('Y-m-d H:i:s'));
        $this->approve($versionTwo, $administrator, $approver);
        $this->assertValidationFailure('effective_at', fn () => $lifecycle->activate($versionTwo->fresh(), $approver));
        $this->assertSame('approved', $versionTwo->fresh()->status);
    }

    public function test_scheduler_activates_due_family_versions_in_effective_version_id_order(): void
    {
        [$administrator, $approver] = $this->governanceActors();
        $lifecycle = app(ScoringRuleLifecycleService::class);
        $firstAt = now()->addDays(2)->startOfMinute();
        $secondAt = now()->addDays(3)->startOfMinute();

        $first = $this->draft('employee_confirmation', $administrator, $firstAt->format('Y-m-d H:i:s'));
        $this->approve($first, $administrator, $approver);
        $lifecycle->activate($first->fresh(), $approver);

        $second = $this->draft('employee_confirmation', $administrator, $secondAt->format('Y-m-d H:i:s'));
        $this->approve($second, $administrator, $approver);
        $lifecycle->activate($second->fresh(), $approver);

        $this->travelTo($secondAt->copy()->addMinute());
        $this->artisan('scoring:activate-scheduled', ['--json' => true])->assertSuccessful();
        $this->travelBack();

        $this->assertSame('superseded', $first->fresh()->status);
        $this->assertSame('active', $second->fresh()->status);
        $this->assertSame(2, ScoringRecalculationRun::query()
            ->whereIn('scoring_rule_id', [$first->id, $second->id])
            ->count());
    }

    public function test_scheduler_fails_closed_when_legacy_duplicate_effective_slots_exist(): void
    {
        [$administrator, $approver] = $this->governanceActors();
        $lifecycle = app(ScoringRuleLifecycleService::class);
        $firstAt = now()->addDays(2)->startOfMinute();
        $secondAt = now()->addDays(3)->startOfMinute();

        $first = $this->draft('recruitment_interview', $administrator, $firstAt->format('Y-m-d H:i:s'));
        $this->approve($first, $administrator, $approver);
        $lifecycle->activate($first->fresh(), $approver);

        $second = $this->draft('recruitment_interview', $administrator, $secondAt->format('Y-m-d H:i:s'));
        $this->approve($second, $administrator, $approver);
        $lifecycle->activate($second->fresh(), $approver);

        DB::table('scoring_rules')->where('id', $second->id)->update(['effective_at' => $firstAt]);
        $this->travelTo($firstAt->copy()->addMinute());
        $this->artisan('scoring:activate-scheduled', ['--json' => true])->assertFailed();
        $this->travelBack();

        $this->assertSame('scheduled', $first->fresh()->status);
        $this->assertSame('scheduled', $second->fresh()->status);
        $this->assertSame(0, ScoringRecalculationRun::query()
            ->whereIn('scoring_rule_id', [$first->id, $second->id])
            ->count());
    }

    /** @return array{0: User, 1: User} */
    private function governanceActors(): array
    {
        $this->seed();

        return [
            User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail(),
            User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail(),
        ];
    }

    private function draft(string $ruleKey, User $creator, ?string $effectiveAt = null): ScoringRule
    {
        return app(ScoringRuleDraftService::class)->create(new CreateScoringRuleData(
            ruleKey: $ruleKey,
            name: str($ruleKey)->replace('_', ' ')->title()->append(' governed rule')->toString(),
            changeReason: 'Create a governed version for scoring integrity regression coverage.',
            effectiveAt: $effectiveAt,
        ), $creator);
    }

    private function approve(ScoringRule $rule, User $creator, User $approver): ScoringRule
    {
        $lifecycle = app(ScoringRuleLifecycleService::class);
        $lifecycle->validateRule($rule->fresh(), $creator);
        $lifecycle->submit($rule->fresh(), $creator);

        return $lifecycle->approve($rule->fresh(), $approver);
    }

    private function tamper(ScoringRule $rule): void
    {
        $configuration = $rule->fresh()->configuration ?? [];
        $configuration['tampered_without_governance'] = true;
        DB::table('scoring_rules')->where('id', $rule->id)->update([
            'configuration' => json_encode($configuration, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }

    private function assertIntegrityFailure(callable $operation): void
    {
        $this->assertValidationFailure('scoring_rule_integrity', $operation);
    }

    private function assertValidationFailure(string $key, callable $operation): void
    {
        try {
            $operation();
            $this->fail("Expected a validation failure for {$key}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($key, $exception->errors());
        }
    }

    private function assertLogicFailure(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected governed scoring-rule immutability to block the mutation.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }
    }
}
