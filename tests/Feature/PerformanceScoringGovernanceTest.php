<?php

namespace Tests\Feature;

use App\Application\Scoring\Actions\ShowScoringOverview;
use App\Domain\Scoring\Services\ScoringRuleCatalog;
use App\Domain\Scoring\Services\ScoringConfigurationChecksum;
use App\Domain\Scoring\Services\ScoringRuleRegister;
use App\Domain\Scoring\Services\ScoreSnapshotWriter;
use App\Domain\Scoring\Services\ScoringSubjectRegistry;
use App\Domain\Scoring\Services\StructuredScoreCalculator;
use App\Domain\Scoring\Support\LogicCenterPermissions;
use App\Jobs\Scoring\ProcessScoringRecalculation;
use App\Models\AttendancePeriodLock;
use App\Models\PayrollAttendanceSnapshot;
use App\Models\PerformanceReview;
use App\Models\PerformanceScoreOverrideRequest;
use App\Models\ScoreSnapshot;
use App\Models\ScoringRecalculationFailure;
use App\Models\ScoringRecalculationRun;
use App\Models\ScoringRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PerformanceScoringGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_performance_governance_schema_is_available(): void
    {
        $this->assertTrue(Schema::hasColumn('performance_reviews', 'score_snapshot_id'));
        $this->assertTrue(Schema::hasColumn('performance_reviews', 'lock_version'));
        $this->assertTrue(Schema::hasTable('performance_score_override_requests'));
        $this->assertTrue(Schema::hasColumns('performance_score_override_requests', [
            'performance_review_id', 'score_snapshot_id', 'requested_by_user_id',
            'decided_by_user_id', 'requested_score', 'status', 'decision_reason',
        ]));
    }

    public function test_score_snapshots_are_append_only_and_pinned_reviews_cannot_lose_evidence(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $rule = $this->createGovernanceRule($admin, 'employee_performance', 'Immutable performance evidence');
        $review = PerformanceReview::query()->firstOrFail();
        $snapshot = ScoreSnapshot::create([
            'company_id' => $rule->company_id,
            'scoring_rule_id' => $rule->id,
            'subject_type' => PerformanceReview::class,
            'subject_id' => $review->id,
            'total_score' => 75,
            'component_scores' => ['kpi' => 75],
            'applied_weights' => ['kpi' => 100],
            'score_band' => 'Good',
            'input_snapshot' => ['kpi' => 75],
            'input_hash' => hash('sha256', 'immutable-performance-evidence'),
            'rule_version' => $rule->version,
            'is_current' => true,
            'is_override' => false,
            'calculated_at' => now(),
            'metadata' => [],
        ]);
        $review->forceFill(['score_snapshot_id' => $snapshot->id])->save();

        try {
            $snapshot->forceFill(['total_score' => 10])->save();
            $this->fail('An immutable score snapshot was updated.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        try {
            $snapshot->fresh()->delete();
            $this->fail('An immutable score snapshot was deleted through Eloquent.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        try {
            DB::table('score_snapshots')->where('id', $snapshot->id)->delete();
            $this->fail('A review-pinned score snapshot was deleted through the query builder.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('score_snapshots', [
            'id' => $snapshot->id,
            'total_score' => 75,
        ]);
        $this->assertSame($snapshot->id, $review->fresh()->score_snapshot_id);

        $snapshot->fresh()->markHistorical();
        $this->assertFalse($snapshot->fresh()->is_current);
        $this->assertSame('75.0000', $snapshot->fresh()->total_score);
    }

    public function test_performance_manager_can_create_only_the_employee_performance_rule_family(): void
    {
        $this->seed();
        $hrManager = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $this->actingAs($hrManager)->post(route('scoring.rules.store'), [
            'rule_key' => 'employee_performance',
            'name' => 'Governed employee performance score',
            'change_reason' => 'Create the governed employee performance formula for review finalization.',
        ])->assertRedirect();

        $this->assertDatabaseHas('scoring_rules', [
            'rule_key' => 'employee_performance',
            'created_by_user_id' => $hrManager->id,
            'status' => 'draft',
        ]);

        $this->actingAs($hrManager)->post(route('scoring.rules.store'), [
            'rule_key' => 'lead_quality',
            'name' => 'Unauthorized business rule',
            'change_reason' => 'This business scoring rule must remain outside HR performance authority.',
            ])->assertForbidden();
    }

    public function test_legacy_performance_view_permission_can_read_the_performance_logic_section(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $viewer = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $configuration = app(ScoringRuleCatalog::class)->defaultConfiguration('employee_performance');
        $rule = ScoringRule::create([
            'company_id' => $viewer->company_id,
            'created_by_user_id' => $admin->id,
            'rule_key' => 'employee_performance',
            'name' => 'Performance rule visible to authorized viewers',
            'version' => 1,
            'status' => 'active',
            'configuration' => $configuration,
            'configuration_checksum' => app(ScoringConfigurationChecksum::class)->make($configuration),
            'change_reason' => 'Verify the legacy read capability remains aligned with Logic Center section access.',
            'activated_at' => now(),
        ]);

        $viewer->role->forceFill(['permissions' => ['performance.view']])->save();
        $viewer->unsetRelation('role');

        $this->assertTrue($viewer->can('viewAny', ScoringRule::class));
        $this->assertTrue($viewer->can('view', $rule));

        $this->actingAs($viewer)
            ->get(route('scoring.index', ['view' => 'performance']))
            ->assertOk()
            ->assertSee('Performance rule visible to authorized viewers')
            ->assertDontSee('New rule draft');

        $viewer->role->forceFill(['permissions' => ['performance.manage']])->save();
        $viewer->unsetRelation('role');
        $this->assertTrue($viewer->can('create', ScoringRule::class));
        $this->assertTrue($viewer->can('createForKey', [ScoringRule::class, 'employee_performance']));

        $viewer->role->forceFill(['permissions' => ['performance.approve']])->save();
        $viewer->unsetRelation('role');
        $pendingRule = ScoringRule::create([
            'company_id' => $viewer->company_id,
            'previous_rule_id' => $rule->id,
            'created_by_user_id' => $admin->id,
            'rule_key' => 'employee_performance',
            'name' => 'Performance rule pending independent approval',
            'version' => 2,
            'status' => 'pending_approval',
            'configuration' => $configuration,
            'configuration_checksum' => app(ScoringConfigurationChecksum::class)->make($configuration),
            'change_reason' => 'Verify legacy performance approval authority without mutating governed evidence.',
            'submitted_at' => now(),
        ]);
        $this->assertTrue($viewer->can('approve', $pendingRule));
    }

    public function test_dedicated_performance_override_roles_can_read_the_linked_formula_snapshot(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $viewer = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $rule = $this->createGovernanceRule($admin, 'employee_performance', 'Override review formula');
        $snapshot = ScoreSnapshot::create([
            'company_id' => $rule->company_id,
            'scoring_rule_id' => $rule->id,
            'subject_type' => PerformanceReview::class,
            'subject_id' => PerformanceReview::query()->value('id'),
            'total_score' => 75,
            'component_scores' => [],
            'applied_weights' => [],
            'score_band' => 'Good',
            'input_snapshot' => [],
            'input_hash' => hash('sha256', 'override-review-formula'),
            'rule_version' => $rule->version,
            'is_current' => true,
            'is_override' => false,
            'calculated_at' => now(),
            'metadata' => [],
        ]);

        foreach ([
            LogicCenterPermissions::PERFORMANCE_OVERRIDE_REQUEST,
            LogicCenterPermissions::PERFORMANCE_OVERRIDE_APPROVE,
        ] as $permission) {
            $viewer->role->forceFill(['permissions' => [$permission]])->save();
            $viewer->unsetRelation('role');

            $this->assertTrue($viewer->can('view', $snapshot));
        }
    }

    public function test_performance_only_viewer_cannot_read_other_scoring_families_in_overview_or_audit(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $viewer = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $performanceRule = $this->createGovernanceRule($admin, 'employee_performance', 'Visible performance family');
        $businessRule = $this->createGovernanceRule($admin, 'lead_quality', 'Hidden business family');

        foreach ([$performanceRule, $businessRule] as $rule) {
            ScoreSnapshot::create([
                'company_id' => $rule->company_id,
                'scoring_rule_id' => $rule->id,
                'subject_type' => User::class,
                'subject_id' => $viewer->id,
                'total_score' => 75,
                'component_scores' => [],
                'applied_weights' => [],
                'score_band' => 'Good',
                'input_snapshot' => [],
                'input_hash' => hash('sha256', $rule->rule_key),
                'rule_version' => $rule->version,
                'is_current' => true,
                'is_override' => false,
                'calculated_at' => now(),
                'metadata' => [],
            ]);
            $run = ScoringRecalculationRun::create([
                'company_id' => $rule->company_id,
                'scoring_rule_id' => $rule->id,
                'triggered_by_user_id' => $admin->id,
                'status' => 'completed',
                'total_records' => 1,
                'processed_records' => 0,
                'failed_records' => 1,
                'completed_at' => now(),
                'metadata' => [],
            ]);
            ScoringRecalculationFailure::create([
                'scoring_recalculation_run_id' => $run->id,
                'subject_type' => User::class,
                'subject_id' => $viewer->id,
                'error_code' => 'fixture_failure',
                'error_message' => $rule->name.' failure evidence',
                'context' => [],
            ]);
        }

        $viewer->role->forceFill(['permissions' => ['performance.view']])->save();
        $viewer->unsetRelation('role');

        foreach (['overview', 'audit'] as $view) {
            $register = app(ScoringRuleRegister::class)->forUser($viewer, ['view' => $view]);

            $this->assertSame(['Visible performance family'], collect($register['rules'])->pluck('name')->all());
            $this->assertSame(['Visible performance family'], collect($register['snapshots'])->pluck('ruleName')->all());
            $this->assertSame(['Visible performance family'], collect($register['runs'])->pluck('rule')->all());
            $this->assertSame(['Visible performance family'], collect($register['failures'])->pluck('rule')->all());
            $this->assertSame(['rules' => 1, 'active' => 1, 'pending' => 0, 'snapshots' => 1], $register['counts']);
        }

        foreach ([
            'performance' => 'Visible performance family',
            'business' => 'Hidden business family',
        ] as $view => $expectedRuleName) {
            $register = app(ScoringRuleRegister::class)->forUser($admin, ['view' => $view]);

            $this->assertSame([$expectedRuleName], collect($register['rules'])->pluck('name')->all());
            $this->assertSame(1, $register['counts']['rules']);
            $this->assertSame(1, $register['counts']['snapshots']);
        }

        $nonScoringView = app(ScoringRuleRegister::class)->forUser($admin, ['view' => 'statutory']);
        $this->assertSame([], $nonScoringView['rules']);
        $this->assertSame([], $nonScoringView['snapshots']);
        $this->assertSame([], $nonScoringView['runs']);
        $this->assertSame([], $nonScoringView['failures']);
        $this->assertSame(['rules' => 0, 'active' => 0, 'pending' => 0, 'snapshots' => 0], $nonScoringView['counts']);
    }

    public function test_create_rule_options_are_filtered_by_the_exact_rule_key_policy(): void
    {
        $this->seed();
        $manager = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $manager->role->forceFill(['permissions' => ['performance.manage']])->save();
        $manager->unsetRelation('role');

        $page = app(ShowScoringOverview::class)->handle($manager, ['view' => 'overview']);

        $this->assertTrue($page->canCreate);
        $this->assertSame(['employee_performance' => 'Employee Performance'], $page->ruleTypes);
        $this->assertTrue($manager->can('createForKey', [ScoringRule::class, 'employee_performance']));
        $this->assertFalse($manager->can('createForKey', [ScoringRule::class, 'lead_quality']));

        $manager->role->forceFill(['permissions' => ['performance.view']])->save();
        $manager->unsetRelation('role');
        $readOnlyPage = app(ShowScoringOverview::class)->handle($manager, ['view' => 'overview']);

        $this->assertFalse($readOnlyPage->canCreate);
        $this->assertSame([], $readOnlyPage->ruleTypes);
    }

    public function test_performance_rule_edits_require_explicit_input_governance_fields(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $this->actingAs($admin)->post(route('scoring.rules.store'), [
            'rule_key' => 'employee_performance',
            'name' => 'Governed performance input contract',
            'change_reason' => 'Create the governed performance input contract for validation.',
        ])->assertRedirect();
        $rule = ScoringRule::query()->where('rule_key', 'employee_performance')->firstOrFail();
        $payload = $this->structuredUpdatePayload($rule);
        unset($payload['criteria'][0]['source']);

        $this->actingAs($admin)->patch(route('scoring.rules.update', $rule), $payload)
            ->assertSessionHasErrors('criteria.0.source');

        $this->assertSame('draft', $rule->fresh()->status);
        $this->assertSame('kpi_achievement', $rule->fresh()->configuration['criteria'][0]['source']);
    }

    public function test_legacy_performance_draft_must_materialize_input_scales_before_validation(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $this->actingAs($admin)->post(route('scoring.rules.store'), [
            'rule_key' => 'employee_performance',
            'name' => 'Legacy performance formula requiring materialization',
            'change_reason' => 'Verify legacy drafts cannot silently inherit a review cycle scale.',
        ])->assertRedirect();
        $rule = ScoringRule::query()->where('rule_key', 'employee_performance')->firstOrFail();
        $configuration = $rule->configuration;
        unset($configuration['criteria'][0]['input_scale']);
        $rule->forceFill([
            'configuration' => $configuration,
            'configuration_checksum' => app(ScoringConfigurationChecksum::class)->make($configuration),
        ])->save();

        $this->actingAs($admin)->patch(route('scoring.rules.validate', $rule))
            ->assertSessionHasErrors('configuration.criteria.0.input_scale');
        $this->assertSame('draft', $rule->fresh()->status);

        $this->actingAs($admin)->patch(route('scoring.rules.update', $rule), $this->structuredUpdatePayload($rule->fresh()))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['min' => 0, 'max' => 5],
            $rule->fresh()->configuration['criteria'][0]['input_scale'],
        );
        $this->actingAs($admin)->patch(route('scoring.rules.validate', $rule->fresh()))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('validated', $rule->fresh()->status);
    }

    public function test_formula_snapshot_override_and_finalization_follow_maker_checker_governance(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $hrManager = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $rule = $this->activatePerformanceRule($admin, $director);
        $review = PerformanceReview::query()->where('review_number', 'PFR-10001')->firstOrFail();
        $review->forceFill([
            'status' => 'manager_submitted',
            'scoring_inputs' => [
                'kpi_achievement' => 4,
                'kra_achievement' => 4,
                'competencies' => 4,
                'behaviour' => 4,
                'attendance' => 4,
                'self_review' => 4,
                'manager_review' => 4,
            ],
        ])->save();

        $this->actingAs($hrManager)->patchJson(route('hr.performance-reviews.calibrate', $review), [
            'lock_version' => $review->lock_version,
            'hr_calibration' => 4,
            'hr_comments' => 'HR verified the complete review evidence and submitted calibration.',
        ])->assertOk();

        $formulaSnapshot = $review->fresh()->scoreSnapshot()->firstOrFail();
        $this->assertSame($rule->id, $formulaSnapshot->scoring_rule_id);
        $this->assertSame($rule->version, $formulaSnapshot->rule_version);
        $this->assertSame('80.0000', $formulaSnapshot->total_score);
        $this->assertSame($rule->configuration_checksum, data_get($formulaSnapshot->metadata, 'configuration_checksum'));

        $this->actingAs($hrManager)->postJson(route('hr.performance-reviews.score-overrides.store', $review), [
            'lock_version' => $review->fresh()->lock_version,
            'requested_score' => 86,
            'reason' => 'Documented calibration evidence supports a governed score adjustment.',
            'evidence' => 'Calibration committee reference CAL-2026-001.',
        ])->assertCreated();

        $overrideRequest = PerformanceScoreOverrideRequest::query()->firstOrFail();
        $this->actingAs($hrManager)->patchJson(route('hr.performance-score-overrides.approve', $overrideRequest), [
            'lock_version' => $review->fresh()->lock_version,
            'decision_reason' => 'The requester must never decide the same governed adjustment.',
        ])->assertForbidden();

        $this->actingAs($director)->patchJson(route('hr.performance-score-overrides.approve', $overrideRequest), [
            'lock_version' => $review->fresh()->lock_version,
            'decision_reason' => 'Independent review confirms the evidence and requested calibrated score.',
        ])->assertOk();

        $overrideRequest->refresh();
        $governedSnapshot = $review->fresh()->scoreSnapshot()->firstOrFail();
        $this->assertSame('approved', $overrideRequest->status);
        $this->assertSame($director->id, $overrideRequest->decided_by_user_id);
        $this->assertTrue($governedSnapshot->is_override);
        $this->assertSame('86.0000', $governedSnapshot->total_score);
        $this->assertSame($overrideRequest->id, data_get($governedSnapshot->metadata, 'performance_override_request_id'));
        $this->assertFalse($formulaSnapshot->fresh()->is_current);

        $this->actingAs($director)->post(route('scoring.snapshots.override', $governedSnapshot), [
            'score' => 90,
            'reason' => 'A generic override endpoint must not bypass performance maker-checker.',
        ])->assertForbidden();

        $this->actingAs($director)->patchJson(route('hr.performance-reviews.close', $review), [
            'lock_version' => $review->fresh()->lock_version,
            'hr_comments' => 'The governed score and independent override decision are complete.',
            'pip_required' => false,
        ])->assertOk();

        $review->refresh();
        $this->assertSame('closed', $review->status);
        $this->assertSame('4.44', $review->final_score);
        $this->assertSame('Excellent', $review->final_rating);
        $this->assertFalse($review->pip_required);
    }

    public function test_formula_required_pip_cannot_be_closed_with_an_empty_plan(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $hrManager = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $this->activatePerformanceRule($admin, $director);
        $review = PerformanceReview::query()->where('review_number', 'PFR-10001')->firstOrFail();
        $employee = $review->employee()->firstOrFail();
        $originalEmploymentStatus = $employee->status;
        $originalMonthlyCtc = $employee->monthly_ctc;
        $originalSalaryAssignmentCount = $employee->salaryAssignments()->count();
        $originalMovementCount = $employee->movements()->count();
        $review->forceFill([
            'status' => 'manager_submitted',
            'scoring_inputs' => [
                'kpi_achievement' => 1, 'kra_achievement' => 1, 'competencies' => 1,
                'behaviour' => 1, 'attendance' => 1, 'self_review' => 1, 'manager_review' => 1,
            ],
        ])->save();

        $this->actingAs($hrManager)->patchJson(route('hr.performance-reviews.calibrate', $review), [
            'lock_version' => $review->lock_version,
            'hr_calibration' => 1,
            'hr_comments' => 'HR verified the evidence before calculating the governed low score.',
        ])->assertOk();

        $this->actingAs($director)->patchJson(route('hr.performance-reviews.close', $review), [
            'lock_version' => $review->fresh()->lock_version,
            'hr_comments' => 'Attempting closure without a meaningful improvement objective.',
            'pip_required' => false,
            'pip_plan' => ['objectives' => ['   '], 'owner' => ''],
        ])->assertUnprocessable()->assertJsonValidationErrors('pip_plan.objectives.0');

        $this->actingAs($director)->patchJson(route('hr.performance-reviews.close', $review), [
            'lock_version' => $review->fresh()->lock_version,
            'hr_comments' => 'Closing with a concrete performance improvement plan and owner.',
            'pip_required' => true,
            'pip_plan' => [
                'objectives' => ['Reach the agreed KPI quality target for four consecutive weeks.'],
                'owner' => 'Reporting Manager',
                'review_frequency' => 'Weekly',
            ],
        ])->assertOk();

        $this->assertTrue($review->fresh()->pip_required);
        $this->assertSame('open', $review->fresh()->pip_status);
        $employee->refresh();
        $this->assertSame($originalEmploymentStatus, $employee->status);
        $this->assertSame($originalMonthlyCtc, $employee->monthly_ctc);
        $this->assertSame($originalSalaryAssignmentCount, $employee->salaryAssignments()->count());
        $this->assertSame($originalMovementCount, $employee->movements()->count());
    }

    public function test_snapshot_pins_content_free_evidence_references_and_stale_supporting_evidence_blocks_closure(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $hrManager = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $this->activatePerformanceRule($admin, $director);
        $review = PerformanceReview::query()->where('review_number', 'PFR-10001')->firstOrFail();
        $this->prepareScorableReview($review);
        $review->forceFill([
            'manager_comments' => 'Manager evidence approved before governed calculation.',
            'strengths' => 'Verified self-review strengths evidence.',
            'improvement_areas' => 'Verified self-review improvement evidence.',
        ])->save();

        $this->actingAs($hrManager)->patchJson(route('hr.performance-reviews.calibrate', $review), [
            'lock_version' => $review->fresh()->lock_version,
            'hr_calibration' => 4,
            'hr_comments' => 'HR calibration evidence approved before governed calculation.',
        ])->assertOk();

        $snapshot = $review->fresh()->scoreSnapshot()->firstOrFail();
        $evidence = data_get($snapshot->metadata, 'performance_evidence');
        $this->assertSame(1, data_get($evidence, 'version'));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) data_get($evidence, 'hash'));
        $this->assertSame(
            'performance_reviews.scoring_inputs.kpi_achievement',
            data_get($evidence, 'references.kpi_achievement.source_path'),
        );
        $this->assertSame(
            'performance_reviews.manager_comments',
            data_get($evidence, 'references.manager_review.supporting_fields.0.path'),
        );
        $this->assertStringNotContainsString(
            'Manager evidence approved before governed calculation.',
            json_encode($evidence, JSON_THROW_ON_ERROR),
        );

        $review->fresh()->forceFill([
            'manager_comments' => 'Manager evidence changed after the governed calculation.',
        ])->save();

        $this->actingAs($director)->patchJson(route('hr.performance-reviews.close', $review), [
            'lock_version' => $review->fresh()->lock_version,
            'hr_comments' => 'Closure must reject evidence changed after calibration.',
            'pip_required' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('score_snapshot');

        $this->assertSame('manager_submitted', $review->fresh()->status);
        $this->assertNull($review->fresh()->final_score);
        $this->assertSame($snapshot->id, $review->fresh()->score_snapshot_id);
    }

    public function test_snapshot_without_current_or_legacy_performance_evidence_cannot_close_a_review(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $hrManager = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $this->activatePerformanceRule($admin, $director);
        $review = PerformanceReview::query()->where('review_number', 'PFR-10001')->firstOrFail();
        $this->prepareScorableReview($review);

        $this->actingAs($hrManager)->patchJson(route('hr.performance-reviews.calibrate', $review), [
            'lock_version' => $review->fresh()->lock_version,
            'hr_calibration' => 4,
            'hr_comments' => 'Create a governed snapshot before testing malformed evidence metadata.',
        ])->assertOk();

        $snapshot = $review->fresh()->scoreSnapshot()->firstOrFail();
        $metadata = $snapshot->metadata;
        unset($metadata['performance_evidence'], $metadata['expected_scoring_inputs_hash']);
        DB::table('score_snapshots')->where('id', $snapshot->id)->update([
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);

        $this->actingAs($director)->patchJson(route('hr.performance-reviews.close', $review), [
            'lock_version' => $review->fresh()->lock_version,
            'hr_comments' => 'A snapshot without verifiable evidence must not finalize the review.',
            'pip_required' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('score_snapshot');

        $this->assertSame('manager_submitted', $review->fresh()->status);
        $this->assertNull($review->fresh()->final_score);
        $this->assertSame($snapshot->id, $review->fresh()->score_snapshot_id);
    }

    public function test_performance_sources_are_normalized_by_the_activated_criterion_contract(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $review = PerformanceReview::query()->where('review_number', 'PFR-10001')->firstOrFail();
        $review->forceFill(['scoring_inputs' => ['kpi_achievement' => 75]])->save();
        $configuration = app(ScoringRuleCatalog::class)->defaultConfiguration('employee_performance');
        $configuration['criteria'] = [[
            'key' => 'delivery_result',
            'label' => 'Delivery result',
            'weight' => 100,
            'max_points' => 100,
            'source' => 'kpi_achievement',
            'normalization' => 'percentage',
            'input_scale' => ['min' => 50, 'max' => 100],
            'required' => true,
            'missing_data_behavior' => 'block',
            'conditions' => [],
        ]];
        $rule = ScoringRule::create([
            'company_id' => $review->company_id,
            'created_by_user_id' => $admin->id,
            'rule_key' => 'employee_performance',
            'name' => 'Mapped percentage performance input',
            'version' => 1,
            'status' => 'active',
            'configuration' => $configuration,
            'configuration_checksum' => app(ScoringConfigurationChecksum::class)->make($configuration),
            'change_reason' => 'Verify source mapping and percentage normalization independently.',
            'activated_at' => now(),
        ]);

        $subject = app(ScoringSubjectRegistry::class)->subject($rule, $review);
        $result = app(StructuredScoreCalculator::class)->calculate($rule, $subject->inputs);

        $this->assertSame(['delivery_result' => 50.0], $subject->inputs);
        $this->assertSame(50.0, $result->totalScore);
    }

    public function test_active_performance_rule_never_falls_back_to_the_review_cycle_scale(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $review = PerformanceReview::query()->where('review_number', 'PFR-10001')->firstOrFail();
        $review->forceFill(['scoring_inputs' => ['kpi_achievement' => 4]])->save();
        $configuration = app(ScoringRuleCatalog::class)->defaultConfiguration('employee_performance');
        $configuration['criteria'] = [[
            'key' => 'delivery_result',
            'label' => 'Delivery result',
            'weight' => 100,
            'max_points' => 100,
            'source' => 'kpi_achievement',
            'normalization' => 'rating_scale',
            'required' => true,
            'missing_data_behavior' => 'block',
            'conditions' => [],
        ]];
        $rule = $this->createActivePerformanceRule(
            $review,
            $admin,
            $configuration,
            'Malformed legacy active performance rule',
        );

        try {
            app(ScoringSubjectRegistry::class)->subject($rule, $review);
            $this->fail('An active performance rule without an explicit input scale must fail closed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('scoring_rule', $exception->errors());
        }
    }

    public function test_recalculation_cannot_replace_the_snapshot_under_a_pending_performance_override(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $hrManager = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $rule = $this->activatePerformanceRule($admin, $director);
        $review = PerformanceReview::query()->where('review_number', 'PFR-10001')->firstOrFail();
        $this->prepareScorableReview($review);

        $this->actingAs($hrManager)->patchJson(route('hr.performance-reviews.calibrate', $review), [
            'lock_version' => $review->lock_version,
            'hr_calibration' => 4,
            'hr_comments' => 'Create the governed formula snapshot before requesting an override.',
        ])->assertOk();
        $review->refresh();
        $currentSnapshotId = $review->score_snapshot_id;
        $snapshotCount = ScoreSnapshot::query()->where('subject_type', PerformanceReview::class)
            ->where('subject_id', $review->id)->count();

        $this->actingAs($hrManager)->postJson(route('hr.performance-reviews.score-overrides.store', $review), [
            'lock_version' => $review->fresh()->lock_version,
            'requested_score' => 84,
            'reason' => 'Keep the formula snapshot pinned until independent override review is complete.',
            'evidence' => 'Calibration evidence CAL-PENDING-001.',
        ])->assertCreated();

        $subject = app(ScoringSubjectRegistry::class)->subject($rule, $review->fresh());
        $result = app(StructuredScoreCalculator::class)->calculate($rule, $subject->inputs);

        try {
            app(ScoreSnapshotWriter::class)->write(
                $rule,
                $result,
                $subject->type,
                $subject->id,
                $subject->inputs,
                $subject->metadata,
            );
            $this->fail('A pending governed override must prevent snapshot replacement.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('performance_review', $exception->errors());
        }

        $this->assertSame($currentSnapshotId, $review->fresh()->score_snapshot_id);
        $this->assertSame($snapshotCount, ScoreSnapshot::query()->where('subject_type', PerformanceReview::class)
            ->where('subject_id', $review->id)->count());
        $this->assertSame('pending', PerformanceScoreOverrideRequest::query()->firstOrFail()->status);
    }

    public function test_stale_calibration_from_a_second_client_is_rejected_without_replacing_the_snapshot(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $hrManager = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $this->activatePerformanceRule($admin, $director);
        $review = PerformanceReview::query()->where('review_number', 'PFR-10001')->firstOrFail();
        $this->prepareScorableReview($review);
        $clientVersion = (int) $review->fresh()->lock_version;

        $this->actingAs($hrManager)->patchJson(route('hr.performance-reviews.calibrate', $review), [
            'lock_version' => $clientVersion,
            'hr_calibration' => 4,
            'hr_comments' => 'The first client submitted the authoritative calibration evidence.',
        ])->assertOk()->assertJsonPath('data.lock_version', $clientVersion + 1);

        $firstSnapshotId = $review->fresh()->score_snapshot_id;
        $snapshotCount = ScoreSnapshot::query()
            ->where('subject_type', PerformanceReview::class)
            ->where('subject_id', $review->id)
            ->count();

        $this->actingAs($hrManager)->patchJson(route('hr.performance-reviews.calibrate', $review), [
            'lock_version' => $clientVersion,
            'hr_calibration' => 3,
            'hr_comments' => 'A stale second client must not replace newer calibration evidence.',
        ])->assertUnprocessable()->assertJsonValidationErrors('lock_version');

        $review->refresh();
        $this->assertSame($clientVersion + 1, $review->lock_version);
        $this->assertSame($firstSnapshotId, $review->score_snapshot_id);
        $this->assertSame('The first client submitted the authoritative calibration evidence.', $review->hr_comments);
        $this->assertSame($snapshotCount, ScoreSnapshot::query()
            ->where('subject_type', PerformanceReview::class)
            ->where('subject_id', $review->id)
            ->count());
    }

    public function test_attendance_reopen_and_refinalization_blocks_close_until_score_is_recalibrated(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $hrManager = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $this->activatePerformanceRule($admin, $director);
        $review = PerformanceReview::query()->where('review_number', 'PFR-10001')->firstOrFail();
        $this->prepareScorableReview($review);
        $review->forceFill(['legacy_manual_scoring' => false])->save();

        $firstLock = $this->createFinalizedAttendanceEvidence($review, 1, 50, 40, 'a', 'b');

        $this->actingAs($hrManager)->patchJson(route('hr.performance-reviews.calibrate', $review), [
            'lock_version' => $review->fresh()->lock_version,
            'hr_calibration' => 4,
            'hr_comments' => 'Calibration uses the first finalized attendance period evidence.',
        ])->assertOk();

        $firstSnapshot = $review->fresh()->scoreSnapshot()->firstOrFail();
        $this->assertSame($firstLock->id, data_get($firstSnapshot->metadata, 'attendance_evidence.period_locks.0.id'));
        $this->assertSame(1, data_get($firstSnapshot->metadata, 'attendance_evidence.period_locks.0.lock_version'));

        $firstLock->forceFill([
            'status' => 'reopened',
            'reopened_by_user_id' => $hrManager->id,
            'reopened_at' => now(),
            'reopen_reason' => 'Correct finalized attendance evidence before review closure.',
            'lock_version' => 2,
        ])->save();
        $secondLock = $this->createFinalizedAttendanceEvidence($review, 2, 50, 45, 'c', 'd');

        $this->actingAs($director)->patchJson(route('hr.performance-reviews.close', $review), [
            'lock_version' => $review->fresh()->lock_version,
            'hr_comments' => 'Closure must reject the score pinned to superseded attendance evidence.',
            'pip_required' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('score_snapshot');

        $this->assertSame('manager_submitted', $review->fresh()->status);

        $this->actingAs($hrManager)->patchJson(route('hr.performance-reviews.calibrate', $review), [
            'lock_version' => $review->fresh()->lock_version,
            'hr_calibration' => 4,
            'hr_comments' => 'HR recalibrated after attendance was reopened and finalized again.',
        ])->assertOk();

        $recalibratedSnapshot = $review->fresh()->scoreSnapshot()->firstOrFail();
        $this->assertNotSame($firstSnapshot->id, $recalibratedSnapshot->id);
        $this->assertSame($secondLock->id, data_get($recalibratedSnapshot->metadata, 'attendance_evidence.period_locks.0.id'));

        $this->actingAs($director)->patchJson(route('hr.performance-reviews.close', $review), [
            'lock_version' => $review->fresh()->lock_version,
            'hr_comments' => 'Closure now uses the recalibrated governed attendance evidence.',
            'pip_required' => false,
        ])->assertOk()->assertJsonPath('data.status', 'closed');
    }

    public function test_recalculation_cannot_repoint_a_closed_performance_review(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $configuration = app(ScoringRuleCatalog::class)->defaultConfiguration('employee_performance');
        $review = PerformanceReview::query()->where('review_number', 'PFR-10001')->firstOrFail();
        $this->prepareScorableReview($review);
        $rule = $this->createActivePerformanceRule($review, $admin, $configuration, 'Closed review race guard');
        $subject = app(ScoringSubjectRegistry::class)->subject($rule, $review);
        $result = app(StructuredScoreCalculator::class)->calculate($rule, $subject->inputs);
        $snapshot = app(ScoreSnapshotWriter::class)->write(
            $rule, $result, $subject->type, $subject->id, $subject->inputs, $subject->metadata,
        );
        $review->refresh()->forceFill(['status' => 'closed'])->save();

        $staleSubject = app(ScoringSubjectRegistry::class)->subject($rule, $review->fresh());
        $staleResult = app(StructuredScoreCalculator::class)->calculate($rule, $staleSubject->inputs);

        try {
            app(ScoreSnapshotWriter::class)->write(
                $rule,
                $staleResult,
                $staleSubject->type,
                $staleSubject->id,
                $staleSubject->inputs,
                $staleSubject->metadata,
            );
            $this->fail('A closed performance review must never be repointed by recalculation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('performance_review', $exception->errors());
        }

        $this->assertSame($snapshot->id, $review->fresh()->score_snapshot_id);
        $this->assertSame(1, ScoreSnapshot::query()->where('subject_type', PerformanceReview::class)
            ->where('subject_id', $review->id)->count());
    }

    public function test_recalculation_retry_is_idempotent_per_run_and_subject(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $configuration = app(ScoringRuleCatalog::class)->defaultConfiguration('employee_performance');
        $review = PerformanceReview::query()->where('review_number', 'PFR-10001')->firstOrFail();
        $this->prepareScorableReview($review);
        $rule = $this->createActivePerformanceRule($review, $admin, $configuration, 'Retry idempotency rule');
        $eligible = app(ScoringSubjectRegistry::class)->eligibleQuery($rule)->count();
        $run = ScoringRecalculationRun::create([
            'company_id' => $rule->company_id,
            'scoring_rule_id' => $rule->id,
            'triggered_by_user_id' => $admin->id,
            'status' => 'pending',
            'total_records' => $eligible,
            'metadata' => ['rule_key' => $rule->rule_key, 'rule_version' => $rule->version],
        ]);

        app()->call([new ProcessScoringRecalculation($run->id), 'handle']);
        $run->refresh();
        $firstProcessed = $run->processed_records;
        $firstFailed = $run->failed_records;
        $firstSnapshotCount = ScoreSnapshot::query()
            ->where('scoring_rule_id', $rule->id)
            ->where('metadata->recalculation_run_id', $run->id)
            ->count();
        $this->assertGreaterThan(0, $firstSnapshotCount);

        $run->forceFill(['status' => 'running', 'completed_at' => null])->save();
        app()->call([new ProcessScoringRecalculation($run->id), 'handle']);
        $run->refresh();

        $this->assertSame('completed', $run->status);
        $this->assertSame($firstProcessed, $run->processed_records);
        $this->assertSame($firstFailed, $run->failed_records);
        $this->assertSame($firstSnapshotCount, ScoreSnapshot::query()
            ->where('scoring_rule_id', $rule->id)
            ->where('metadata->recalculation_run_id', $run->id)
            ->count());
    }

    /** @param array<string, mixed> $configuration */
    private function createActivePerformanceRule(
        PerformanceReview $review,
        User $creator,
        array $configuration,
        string $name,
    ): ScoringRule {
        return ScoringRule::create([
            'company_id' => $review->company_id,
            'created_by_user_id' => $creator->id,
            'rule_key' => 'employee_performance',
            'name' => $name,
            'version' => 1,
            'status' => 'active',
            'configuration' => $configuration,
            'configuration_checksum' => app(ScoringConfigurationChecksum::class)->make($configuration),
            'change_reason' => 'Create an active rule for isolated recalculation governance verification.',
            'activated_at' => now(),
        ]);
    }

    private function createGovernanceRule(User $creator, string $ruleKey, string $name): ScoringRule
    {
        $configuration = app(ScoringRuleCatalog::class)->defaultConfiguration($ruleKey);

        return ScoringRule::create([
            'company_id' => $creator->company_id,
            'created_by_user_id' => $creator->id,
            'rule_key' => $ruleKey,
            'name' => $name,
            'version' => 1,
            'status' => 'active',
            'configuration' => $configuration,
            'configuration_checksum' => app(ScoringConfigurationChecksum::class)->make($configuration),
            'change_reason' => 'Create isolated authorization evidence for the governed Logic Center register.',
            'activated_at' => now(),
        ]);
    }

    private function prepareScorableReview(PerformanceReview $review): void
    {
        $review->forceFill([
            'status' => 'manager_submitted',
            'scoring_inputs' => [
                'kpi_achievement' => 4,
                'kra_achievement' => 4,
                'competencies' => 4,
                'behaviour' => 4,
                'attendance' => 4,
                'self_review' => 4,
                'manager_review' => 4,
                'hr_calibration' => 4,
            ],
        ])->save();
    }

    private function createFinalizedAttendanceEvidence(
        PerformanceReview $review,
        int $version,
        int $scheduledDays,
        float $payableDays,
        string $lockHashCharacter,
        string $snapshotHashCharacter,
    ): AttendancePeriodLock {
        $lock = AttendancePeriodLock::create([
            'company_id' => $review->company_id,
            'period_start' => $review->period_start,
            'period_end' => $review->period_end,
            'version' => $version,
            'status' => 'finalized',
            'finalized_at' => now(),
            'source_hash' => str_repeat($lockHashCharacter, 64),
            'lock_version' => 1,
        ]);

        PayrollAttendanceSnapshot::create([
            'attendance_period_lock_id' => $lock->id,
            'company_id' => $review->company_id,
            'employee_id' => $review->employee_id,
            'period_start' => $review->period_start,
            'period_end' => $review->period_end,
            'scheduled_days' => $scheduledDays,
            'present_days' => (int) floor($payableDays),
            'paid_leave_days' => 0,
            'unpaid_days' => max(0, $scheduledDays - (int) floor($payableDays)),
            'worked_minutes' => (int) floor($payableDays * 480),
            'payable_days' => $payableDays,
            'source_hash' => str_repeat($snapshotHashCharacter, 64),
            'calculation_trace' => ['fixture' => 'performance-attendance-evidence'],
        ]);

        return $lock;
    }

    private function activatePerformanceRule(User $creator, User $approver): ScoringRule
    {
        $this->actingAs($creator)->post(route('scoring.rules.store'), [
            'rule_key' => 'employee_performance',
            'name' => 'Authoritative employee performance score',
            'change_reason' => 'Create the authoritative versioned employee performance formula.',
        ])->assertRedirect();
        $rule = ScoringRule::query()->where('rule_key', 'employee_performance')->firstOrFail();
        $this->actingAs($creator)->patch(route('scoring.rules.validate', $rule))->assertRedirect();
        $this->actingAs($creator)->patch(route('scoring.rules.submit', $rule))->assertRedirect();
        $this->actingAs($approver)->patch(route('scoring.rules.approve', $rule))->assertRedirect();
        $this->actingAs($approver)->patch(route('scoring.rules.activate', $rule))->assertRedirect();

        return $rule->fresh();
    }

    /** @return array<string, mixed> */
    private function structuredUpdatePayload(ScoringRule $rule): array
    {
        $configuration = $rule->configuration;

        return [
            'name' => $rule->name,
            'change_reason' => 'Update the governed performance criteria with documented approval evidence.',
            'effective_at' => null,
            'criteria' => $configuration['criteria'],
            'bands' => $configuration['bands'],
            'rating_min' => $configuration['rating_scale']['min'],
            'rating_max' => $configuration['rating_scale']['max'],
            'passing_score' => $configuration['thresholds']['passing_score'],
            'pip_score' => $configuration['thresholds']['pip_score'],
            'rounding_method' => $configuration['rounding']['method'],
            'rounding_precision' => $configuration['rounding']['precision'],
            'minimum_sample_size' => $configuration['minimum_sample_size'],
            'override_allowed' => (int) $configuration['override']['allowed'],
            'override_reason_required' => (int) $configuration['override']['reason_required'],
        ];
    }
}
