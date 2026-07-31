<?php

namespace Tests\Feature;

use App\Application\Scoring\DTOs\CreateScoringRuleData;
use App\Domain\Scoring\Services\ScoreSnapshotWriter;
use App\Domain\Scoring\Services\ScoringConfigurationChecksum;
use App\Domain\Scoring\Services\ScoringRuleCatalog;
use App\Domain\Scoring\Services\ScoringRuleDraftService;
use App\Domain\Scoring\Services\ScoringRuleLifecycleService;
use App\Domain\Scoring\Services\StructuredScoreCalculator;
use App\Models\AuditEvent;
use App\Models\ScoreSnapshot;
use App\Models\ScoringAggregateSubject;
use App\Models\ScoringRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ScoringSchedulerAndSnapshotIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_generic_snapshot_writes_use_a_stable_subject_mutex_and_leave_only_one_current_snapshot(): void
    {
        [$administrator] = $this->governanceActors();
        $rule = $this->activeRule($administrator, 'lead_quality');
        $inputs = [
            'budget_fit' => 'confirmed_fit',
            'decision_authority' => 'decision_maker',
            'requirement_clarity' => 'urgent_specific',
            'purchase_timeline' => 'immediate',
        ];
        $result = app(StructuredScoreCalculator::class)->calculate($rule, $inputs);
        $writer = app(ScoreSnapshotWriter::class);

        $first = $writer->write($rule, $result, 'lead', 987, $inputs);
        $second = $writer->write($rule, $result, 'lead', 987, $inputs);

        $this->assertFalse($first->fresh()->is_current);
        $this->assertTrue($second->fresh()->is_current);
        $this->assertSame(1, ScoreSnapshot::query()
            ->where('company_id', $rule->company_id)
            ->where('subject_type', 'lead')
            ->where('subject_id', 987)
            ->where('is_current', true)
            ->count());
        $this->assertSame(1, ScoringAggregateSubject::query()
            ->where('company_id', $rule->company_id)
            ->where('subject_type', 'score_snapshot_mutex')
            ->count());
    }

    public function test_scheduler_records_safe_failure_evidence_and_continues_with_later_rules(): void
    {
        [$administrator, $approver] = $this->governanceActors();
        $invalidAt = now()->addDays(2)->startOfMinute();
        $validAt = now()->addDays(3)->startOfMinute();
        $invalid = $this->scheduledRule('project_health', $administrator, $approver, $invalidAt);
        $valid = $this->scheduledRule('vendor_performance', $administrator, $approver, $validAt);
        DB::table('scoring_rules')->where('id', $invalid->id)->update(['approved_by_user_id' => null]);

        $this->travelTo($validAt->copy()->addMinute());
        $this->artisan('scoring:activate-scheduled', ['--json' => true])->assertFailed();
        $this->travelBack();

        $this->assertSame('scheduled', $invalid->fresh()->status);
        $this->assertSame('active', $valid->fresh()->status);
        $audit = AuditEvent::query()
            ->where('event_type', 'scoring.rule.scheduled_activation_failed')
            ->where('auditable_id', $invalid->id)
            ->firstOrFail();
        $metadata = $audit->metadata;
        $this->assertSame($invalid->id, $metadata['rule_id']);
        $this->assertSame($invalid->version, $metadata['rule_version']);
        $this->assertSame('governance_actor_resolution', $metadata['failure_stage']);
        $this->assertSame('approver_missing', $metadata['error_classification']);
        $this->assertTrue($metadata['retry_context']['scheduler_retry_eligible']);
        $this->assertSame('retry_on_next_scheduler_run', $metadata['retry_context']['next_action']);
        $this->assertArrayNotHasKey('configuration', $metadata);
        $this->assertArrayNotHasKey('configuration_checksum', $metadata);
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

    private function activeRule(User $creator, string $ruleKey): ScoringRule
    {
        $configuration = app(ScoringRuleCatalog::class)->defaultConfiguration($ruleKey);

        return ScoringRule::create([
            'company_id' => $creator->company_id,
            'created_by_user_id' => $creator->id,
            'rule_key' => $ruleKey,
            'name' => str($ruleKey)->replace('_', ' ')->title()->append(' current-snapshot guard')->toString(),
            'version' => 1,
            'status' => 'active',
            'configuration' => $configuration,
            'configuration_checksum' => app(ScoringConfigurationChecksum::class)->make($configuration),
            'change_reason' => 'Verify first-write concurrency is serialized by a stable governed subject mutex.',
            'activated_at' => now(),
        ]);
    }

    private function scheduledRule(
        string $ruleKey,
        User $creator,
        User $approver,
        \DateTimeInterface $effectiveAt,
    ): ScoringRule {
        $rule = app(ScoringRuleDraftService::class)->create(new CreateScoringRuleData(
            ruleKey: $ruleKey,
            name: str($ruleKey)->replace('_', ' ')->title()->append(' scheduled failure evidence')->toString(),
            changeReason: 'Verify scheduled activation records safe failure evidence and continues processing.',
            effectiveAt: $effectiveAt->format('Y-m-d H:i:s'),
        ), $creator);
        $lifecycle = app(ScoringRuleLifecycleService::class);
        $lifecycle->validateRule($rule, $creator);
        $lifecycle->submit($rule->fresh(), $creator);
        $lifecycle->approve($rule->fresh(), $approver);
        $lifecycle->activate($rule->fresh(), $approver);

        return $rule->fresh();
    }
}
