<?php

namespace Tests\Feature;

use App\Models\ScoringRule;
use App\Models\User;
use App\Domain\Scoring\Services\ActiveScoringRuleResolver;
use App\Domain\Scoring\Services\StructuredScoreCalculator;
use App\Application\Scoring\Actions\CalculateAndStoreScore;
use App\Models\ScoreSnapshot;
use App\Models\Lead;
use App\Models\ScoringRecalculationRun;
use App\Models\UserNotification;
use App\Models\EmployeeConfirmationCase;
use App\Models\PerformanceReview;
use App\Models\Interview;
use App\Models\Vendor;
use App\Models\Project;
use App\Models\ServiceTicket;
use App\Models\EmployeeExitInterview;
use App\Models\ScoringAggregateSubject;
use App\Services\Builder360\Builder360Bootstrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ScoringLogicTest extends TestCase
{
    use RefreshDatabase;

    public function test_scoring_schema_preserves_rules_snapshots_and_recalculation_evidence(): void
    {
        $this->assertTrue(Schema::hasColumns('scoring_rules', ['rule_key', 'version', 'configuration', 'configuration_checksum', 'previous_rule_id', 'activated_at']));
        $this->assertTrue(Schema::hasColumns('score_snapshots', ['subject_type', 'subject_id', 'total_score', 'component_scores', 'applied_weights', 'input_hash', 'rule_version', 'override_reason']));
        $this->assertTrue(Schema::hasTable('scoring_recalculation_runs'));
        $this->assertTrue(Schema::hasTable('scoring_recalculation_failures'));
        $this->assertTrue(Schema::hasTable('scoring_aggregate_subjects'));
        $this->assertTrue(Schema::hasColumn('performance_reviews', 'scoring_inputs'));
        $this->assertTrue(Schema::hasColumn('projects', 'scoring_inputs'));
        $this->assertTrue(Schema::hasColumn('vendors', 'scoring_inputs'));
    }

    public function test_authorized_roles_can_open_server_rendered_scoring_workspace(): void
    {
        $this->seed();
        $systemAdmin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $this->actingAs($systemAdmin)
            ->get(route('scoring.index'))
            ->assertOk()
            ->assertSee('Logic Center Overview')
            ->assertSee('Lead Scoring')
            ->assertSee('Employee Performance')
            ->assertSee('Versions, Recalculation &amp; Audit', false)
            ->assertSee('New rule draft')
            ->assertDontSee('id="root"', false);
    }

    public function test_read_only_scoring_role_cannot_create_rules(): void
    {
        $this->seed();
        $auditor = User::query()->where('email', 'ishaan.trivedi@builder360.test')->firstOrFail();

        $this->actingAs($auditor)
            ->get(route('scoring.index', ['view' => 'rule-history']))
            ->assertOk()
            ->assertSee('Rule Change History')
            ->assertDontSee('New rule draft');

        $this->assertFalse($auditor->can('create', ScoringRule::class));
    }

    public function test_external_roles_cannot_open_scoring_workspace(): void
    {
        $this->seed();
        $buyer = User::query()->where('email', 'rohan.shah@example.test')->firstOrFail();

        $this->actingAs($buyer)->get(route('scoring.index'))->assertForbidden();
    }

    public function test_scoring_index_rejects_unknown_views_and_filters(): void
    {
        $this->seed();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($director)
            ->get(route('scoring.index', ['view' => 'executable-formula', 'class_name' => 'Dangerous']))
            ->assertSessionHasErrors(['view', 'class_name']);
    }

    public function test_system_admin_can_create_versioned_structured_rule_drafts(): void
    {
        $this->seed();
        $systemAdmin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $payload = [
            'rule_key' => 'lead_quality',
            'name' => 'Lead Qualification Score',
            'change_reason' => 'Establish the governed lead qualification scoring baseline.',
            'effective_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ];

        $this->actingAs($systemAdmin)
            ->post(route('scoring.rules.store'), $payload)
            ->assertRedirect(route('scoring.index', ['view' => 'rule-history']))
            ->assertSessionHas('status');

        $first = ScoringRule::query()->where('rule_key', 'lead_quality')->firstOrFail();
        $this->assertSame(1, $first->version);
        $this->assertSame('draft', $first->status);
        $this->assertCount(4, $first->configuration['criteria']);
        $this->assertSame(64, strlen($first->configuration_checksum));

        $this->actingAs($systemAdmin)->post(route('scoring.rules.store'), $payload)->assertRedirect();
        $second = ScoringRule::query()->where('rule_key', 'lead_quality')->latest('version')->firstOrFail();
        $this->assertSame(2, $second->version);
        $this->assertSame($first->id, $second->previous_rule_id);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'scoring.rule.draft_created', 'auditable_id' => $second->id]);
    }

    public function test_scoring_draft_rejects_unknown_rule_keys_and_short_reasons(): void
    {
        $this->seed();
        $systemAdmin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $this->actingAs($systemAdmin)
            ->post(route('scoring.rules.store'), [
                'rule_key' => 'php_callback',
                'name' => 'Unsafe rule',
                'change_reason' => 'short',
            ])
            ->assertSessionHasErrors(['rule_key', 'change_reason']);

        $this->assertDatabaseCount('scoring_rules', 0);
    }

    public function test_rule_lifecycle_enforces_validation_approval_separation_and_atomic_activation(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $payload = [
            'rule_key' => 'project_health',
            'name' => 'Project Health Score',
            'change_reason' => 'Establish the governed project health scoring baseline.',
        ];

        $this->actingAs($admin)->post(route('scoring.rules.store'), $payload)->assertRedirect();
        $rule = ScoringRule::query()->where('rule_key', 'project_health')->firstOrFail();

        $this->actingAs($admin)->patch(route('scoring.rules.validate', $rule))->assertRedirect()->assertSessionDoesntHaveErrors();
        $this->assertSame('validated', $rule->fresh()->status);
        $this->actingAs($admin)->patch(route('scoring.rules.submit', $rule))->assertRedirect();
        $this->assertSame('pending_approval', $rule->fresh()->status);

        $this->actingAs($admin)->patch(route('scoring.rules.approve', $rule))->assertForbidden();
        $this->actingAs($director)->patch(route('scoring.rules.approve', $rule))->assertRedirect();
        $this->assertSame('approved', $rule->fresh()->status);

        $this->actingAs($director)->patch(route('scoring.rules.activate', $rule))->assertRedirect();
        $this->assertSame('active', $rule->fresh()->status);
        $this->assertNotNull($rule->fresh()->activated_at);

        $this->actingAs($admin)->post(route('scoring.rules.store'), $payload)->assertRedirect();
        $replacement = ScoringRule::query()->where('rule_key', 'project_health')->latest('version')->firstOrFail();
        $this->actingAs($admin)->patch(route('scoring.rules.validate', $replacement))->assertRedirect();
        $this->actingAs($admin)->patch(route('scoring.rules.submit', $replacement))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.approve', $replacement))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.activate', $replacement))->assertRedirect();

        $this->assertSame('superseded', $rule->fresh()->status);
        $this->assertSame('active', $replacement->fresh()->status);
        $this->assertSame(1, ScoringRule::query()->where('rule_key', 'project_health')->where('status', 'active')->count());
    }

    public function test_future_effective_rule_is_scheduled_instead_of_activated_early(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $effectiveAt = now()->addWeek();
        $this->actingAs($admin)->post(route('scoring.rules.store'), [
            'rule_key' => 'customer_satisfaction',
            'name' => 'Customer Satisfaction Score',
            'change_reason' => 'Schedule the governed customer satisfaction baseline.',
            'effective_at' => $effectiveAt->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $rule = ScoringRule::query()->where('rule_key', 'customer_satisfaction')->firstOrFail();
        $this->actingAs($admin)->patch(route('scoring.rules.validate', $rule))->assertRedirect();
        $this->actingAs($admin)->patch(route('scoring.rules.submit', $rule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.approve', $rule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.activate', $rule))->assertRedirect();

        $this->assertSame('scheduled', $rule->fresh()->status);
        $this->assertNull($rule->fresh()->activated_at);

        $this->travelTo($effectiveAt->copy()->addMinute());
        $this->artisan('scoring:activate-scheduled', ['--json' => true])->assertSuccessful();
        $this->assertSame('active', $rule->fresh()->status);
        $this->assertNotNull($rule->fresh()->activated_at);
        $this->assertDatabaseHas('scoring_recalculation_runs', ['scoring_rule_id' => $rule->id, 'status' => 'completed']);
        $this->travelBack();
    }

    public function test_authorized_admin_can_edit_a_rule_through_structured_fields(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $this->actingAs($admin)->post(route('scoring.rules.store'), [
            'rule_key' => 'lead_quality',
            'name' => 'Lead Quality Baseline',
            'change_reason' => 'Create a controlled lead scoring baseline for review.',
        ])->assertRedirect();
        $rule = ScoringRule::query()->where('rule_key', 'lead_quality')->firstOrFail();

        $this->actingAs($admin)->get(route('scoring.rules.edit', $rule))
            ->assertOk()
            ->assertSee('Edit scoring rule')
            ->assertSee('Scoring criteria')
            ->assertSee('Score bands')
            ->assertSee('Save rule draft')
            ->assertDontSee('configuration_json');

        $payload = $this->structuredUpdatePayload($rule);
        $payload['name'] = 'Lead Quality Score v1';
        $payload['criteria'][0]['conditions'] = [[
            'key' => 'budget_verified', 'label' => 'Budget verified', 'operator' => 'boolean',
            'value' => 'yes', 'points' => 10,
        ]];
        $originalChecksum = $rule->configuration_checksum;

        $this->actingAs($admin)->patch(route('scoring.rules.update', $rule), $payload)
            ->assertRedirect(route('scoring.index', ['view' => 'rule-history']))
            ->assertSessionHas('status');

        $updated = $rule->fresh();
        $this->assertSame('Lead Quality Score v1', $updated->name);
        $this->assertSame('draft', $updated->status);
        $this->assertNotSame($originalChecksum, $updated->configuration_checksum);
        $this->assertSame('budget_verified', $updated->configuration['criteria'][0]['conditions'][0]['key']);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'scoring.rule.draft_updated', 'auditable_id' => $rule->id]);
    }

    public function test_structured_rule_update_rejects_invalid_weights_and_ignores_executable_payloads(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $this->actingAs($admin)->post(route('scoring.rules.store'), [
            'rule_key' => 'lead_quality', 'name' => 'Lead Score',
            'change_reason' => 'Create the lead scoring draft for safe editing.',
        ])->assertRedirect();
        $rule = ScoringRule::query()->where('rule_key', 'lead_quality')->firstOrFail();
        $payload = $this->structuredUpdatePayload($rule);
        $payload['criteria'][0]['weight'] = 10;
        $payload['configuration'] = ['formula' => 'dangerous()'];

        $this->actingAs($admin)->patch(route('scoring.rules.update', $rule), $payload)
            ->assertSessionHasErrors(['configuration.criteria']);

        $this->assertArrayNotHasKey('formula', $rule->fresh()->configuration);
    }

    public function test_read_only_scoring_role_cannot_edit_rule_drafts(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $auditor = User::query()->where('email', 'ishaan.trivedi@builder360.test')->firstOrFail();
        $this->actingAs($admin)->post(route('scoring.rules.store'), [
            'rule_key' => 'project_health', 'name' => 'Project Health',
            'change_reason' => 'Create the project health scoring draft for review.',
        ])->assertRedirect();
        $rule = ScoringRule::query()->where('rule_key', 'project_health')->firstOrFail();

        $this->actingAs($auditor)->get(route('scoring.rules.edit', $rule))->assertForbidden();
        $this->actingAs($auditor)->patch(route('scoring.rules.update', $rule), $this->structuredUpdatePayload($rule))->assertForbidden();
    }

    public function test_clone_reject_retire_and_rollback_preserve_versioned_lifecycle(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $createPayload = [
            'rule_key' => 'vendor_performance', 'name' => 'Vendor Performance Score',
            'change_reason' => 'Create a vendor performance scoring baseline for lifecycle verification.',
        ];
        $this->actingAs($admin)->post(route('scoring.rules.store'), $createPayload)->assertRedirect();
        $first = ScoringRule::query()->where('rule_key', 'vendor_performance')->firstOrFail();

        $this->actingAs($admin)->patch(route('scoring.rules.validate', $first))->assertRedirect();
        $this->actingAs($admin)->patch(route('scoring.rules.submit', $first))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.reject', $first), [
            'reason' => 'Clarify vendor delivery evidence before this rule can be approved.',
        ])->assertRedirect();
        $this->assertSame('rejected', $first->fresh()->status);
        $this->assertSame('Clarify vendor delivery evidence before this rule can be approved.', $first->fresh()->metadata['rejection_reason']);

        $this->actingAs($admin)->patch(route('scoring.rules.validate', $first))->assertRedirect();
        $this->actingAs($admin)->patch(route('scoring.rules.submit', $first))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.approve', $first))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.activate', $first))->assertRedirect();

        $this->actingAs($admin)->post(route('scoring.rules.clone', $first), [
            'change_reason' => 'Clone the active version to prepare the next controlled vendor scoring release.',
        ])->assertRedirect();
        $clone = ScoringRule::query()->where('rule_key', 'vendor_performance')->latest('version')->firstOrFail();
        $this->assertSame(2, $clone->version);
        $this->assertSame($first->configuration_checksum, $clone->configuration_checksum);
        $this->assertSame($first->id, $clone->metadata['cloned_from_rule_id']);

        $this->actingAs($director)->patch(route('scoring.rules.retire', $first), [
            'reason' => 'Retire the active vendor rule while the controlled replacement is completed.',
        ])->assertRedirect();
        $this->assertSame('retired', $first->fresh()->status);

        $this->actingAs($admin)->post(route('scoring.rules.rollback', $first), [
            'change_reason' => 'Prepare a rollback draft from the proven first vendor scoring version.',
        ])->assertRedirect();
        $rollback = ScoringRule::query()->where('rule_key', 'vendor_performance')->latest('version')->firstOrFail();
        $this->assertSame(3, $rollback->version);
        $this->assertSame($first->id, $rollback->metadata['rollback_source_rule_id']);
        $this->assertSame('draft', $rollback->status);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'scoring.rule.rollback_draft_created', 'auditable_id' => $rollback->id]);
    }

    public function test_rule_inspection_compares_versions_previews_impact_and_exports_evidence(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $payload = [
            'rule_key' => 'lead_quality', 'name' => 'Lead Qualification Score',
            'change_reason' => 'Create a lead scoring version for inspection and impact verification.',
        ];
        $this->actingAs($admin)->post(route('scoring.rules.store'), $payload)->assertRedirect();
        $first = ScoringRule::query()->where('rule_key', 'lead_quality')->firstOrFail();
        $this->actingAs($admin)->post(route('scoring.rules.clone', $first), [
            'change_reason' => 'Clone the lead scoring configuration for controlled comparison testing.',
        ])->assertRedirect();
        $second = ScoringRule::query()->where('rule_key', 'lead_quality')->latest('version')->firstOrFail();
        $update = $this->structuredUpdatePayload($second);
        $update['criteria'][0]['weight'] = 30;
        $update['criteria'][1]['weight'] = 20;
        $this->actingAs($admin)->patch(route('scoring.rules.update', $second), $update)->assertRedirect();

        $this->actingAs($admin)->get(route('scoring.rules.show', ['scoringRule' => $second, 'compare_to' => $first->id]))
            ->assertOk()
            ->assertSee('Eligible records')
            ->assertSee('Open leads eligible')
            ->assertSee('Compared with version 1')
            ->assertSee('Criteria')
            ->assertSee('Rule activity');

        $this->actingAs($admin)->get(route('scoring.rules.export', $second))
            ->assertOk()
            ->assertDownload('scoring-lead_quality-v2.json');
        $this->assertDatabaseHas('audit_events', ['event_type' => 'scoring.rule.exported', 'auditable_id' => $second->id]);
    }

    public function test_rule_comparison_rejects_a_version_from_another_scoring_area(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        foreach ([['lead_quality', 'Lead Score'], ['project_health', 'Project Score']] as [$key, $name]) {
            $this->actingAs($admin)->post(route('scoring.rules.store'), [
                'rule_key' => $key, 'name' => $name,
                'change_reason' => 'Create a scoring version for comparison boundary verification.',
            ])->assertRedirect();
        }
        $lead = ScoringRule::query()->where('rule_key', 'lead_quality')->firstOrFail();
        $project = ScoringRule::query()->where('rule_key', 'project_health')->firstOrFail();

        $this->actingAs($admin)->get(route('scoring.rules.show', ['scoringRule' => $lead, 'compare_to' => $project->id]))
            ->assertSessionHasErrors(['compare_to']);
    }

    public function test_active_rule_resolver_and_structured_calculator_produce_reproducible_breakdown(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $this->actingAs($admin)->post(route('scoring.rules.store'), [
            'rule_key' => 'lead_quality', 'name' => 'Lead Quality Calculation',
            'change_reason' => 'Create an active structured rule for deterministic calculation verification.',
        ])->assertRedirect();
        $rule = ScoringRule::query()->where('rule_key', 'lead_quality')->firstOrFail();
        $this->actingAs($admin)->patch(route('scoring.rules.validate', $rule))->assertRedirect();
        $this->actingAs($admin)->patch(route('scoring.rules.submit', $rule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.approve', $rule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.activate', $rule))->assertRedirect();

        $resolved = app(ActiveScoringRuleResolver::class)->resolve($rule->company_id, 'lead_quality');
        $this->assertNotNull($resolved);
        $this->assertSame($rule->id, $resolved->id);
        $inputs = ['budget_fit' => 25, 'decision_authority' => 20, 'requirement_clarity' => 15, 'purchase_timeline' => 10];
        $result = app(StructuredScoreCalculator::class)->calculate($resolved, $inputs);
        $sameInputsDifferentOrder = array_reverse($inputs, true);
        $repeat = app(StructuredScoreCalculator::class)->calculate($resolved, $sameInputsDifferentOrder);

        $this->assertSame(70.0, $result->totalScore);
        $this->assertSame('warm', $result->scoreBand);
        $this->assertCount(4, $result->componentScores);
        $this->assertSame(25.0, $result->appliedWeights['budget_fit']);
        $this->assertSame($result->inputHash, $repeat->inputHash);
        $this->assertSame($rule->version, $result->ruleVersion);
    }

    public function test_calculated_snapshots_are_immutable_and_overrides_create_linked_evidence(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $auditor = User::query()->where('email', 'ishaan.trivedi@builder360.test')->firstOrFail();
        $this->actingAs($admin)->post(route('scoring.rules.store'), [
            'rule_key' => 'lead_quality', 'name' => 'Lead Snapshot Rule',
            'change_reason' => 'Create an active scoring version for immutable snapshot verification.',
        ])->assertRedirect();
        $rule = ScoringRule::query()->where('rule_key', 'lead_quality')->firstOrFail();
        $this->actingAs($admin)->patch(route('scoring.rules.validate', $rule))->assertRedirect();
        $this->actingAs($admin)->patch(route('scoring.rules.submit', $rule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.approve', $rule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.activate', $rule))->assertRedirect();

        $snapshot = app(CalculateAndStoreScore::class)->handle(
            $rule->company_id, 'lead_quality', 'lead', 9001,
            ['budget_fit' => 25, 'decision_authority' => 25, 'requirement_clarity' => 20, 'purchase_timeline' => 15],
        );
        $this->assertSame('85.0000', $snapshot->total_score);
        $this->assertTrue($snapshot->is_current);
        $this->assertFalse($snapshot->is_override);
        $this->assertSame($rule->version, $snapshot->rule_version);

        $this->actingAs($admin)->post(route('scoring.rules.store'), [
            'rule_key' => 'project_health', 'name' => 'Independent Project Score',
            'change_reason' => 'Verify current snapshots remain independent across different scoring areas.',
        ])->assertRedirect();
        $projectRule = ScoringRule::query()->where('rule_key', 'project_health')->firstOrFail();
        $this->actingAs($admin)->patch(route('scoring.rules.validate', $projectRule))->assertRedirect();
        $this->actingAs($admin)->patch(route('scoring.rules.submit', $projectRule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.approve', $projectRule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.activate', $projectRule))->assertRedirect();
        app(CalculateAndStoreScore::class)->handle(
            $rule->company_id, 'project_health', 'lead', 9001,
            array_fill_keys(collect($projectRule->configuration['criteria'])->pluck('key')->all(), 50),
        );
        $this->assertTrue($snapshot->fresh()->is_current, 'A different scoring area must not supersede this snapshot.');

        $this->actingAs($auditor)->post(route('scoring.snapshots.override', $snapshot), [
            'score' => 72, 'reason' => 'Auditor must not be permitted to override this score.',
        ])->assertForbidden();
        $this->actingAs($admin)->post(route('scoring.snapshots.override', $snapshot), [
            'score' => 72, 'reason' => 'Verified source evidence requires a documented score correction.',
        ])->assertRedirect();

        $override = ScoreSnapshot::query()->where('overridden_from_snapshot_id', $snapshot->id)->firstOrFail();
        $this->assertFalse($snapshot->fresh()->is_current);
        $this->assertTrue($override->is_current);
        $this->assertTrue($override->is_override);
        $this->assertSame('72.0000', $override->total_score);
        $this->assertSame('Verified source evidence requires a documented score correction.', $override->override_reason);
        $this->assertSame($admin->id, $override->overridden_by_user_id);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'scoring.score.overridden', 'auditable_id' => $override->id]);
    }

    public function test_activation_recalculates_eligible_leads_reconciles_failures_and_notifies_authorized_users(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $auditor = User::query()->where('email', 'ishaan.trivedi@builder360.test')->firstOrFail();
        $sales = User::query()->where('email', 'priya.nair@builder360.test')->firstOrFail();
        $this->actingAs($admin)->post(route('scoring.rules.store'), [
            'rule_key' => 'lead_quality', 'name' => 'Lead Recalculation Rule',
            'change_reason' => 'Create an active lead rule for recalculation reconciliation verification.',
        ])->assertRedirect();
        $rule = ScoringRule::query()->where('rule_key', 'lead_quality')->firstOrFail();
        $this->actingAs($admin)->patch(route('scoring.rules.validate', $rule))->assertRedirect();
        $this->actingAs($admin)->patch(route('scoring.rules.submit', $rule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.approve', $rule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.activate', $rule))->assertRedirect();

        $run = ScoringRecalculationRun::query()->where('scoring_rule_id', $rule->id)->latest()->firstOrFail();
        $expected = Lead::query()->where('company_id', $rule->company_id)->whereNotIn('status', ['won', 'lost'])->count();
        $this->assertSame('completed', $run->status);
        $this->assertSame($expected, $run->total_records);
        $this->assertSame($run->total_records, $run->processed_records + $run->failed_records);
        $this->assertSame($run->failed_records, $run->failures()->count());
        $this->assertGreaterThan(0, ScoreSnapshot::query()->where('scoring_rule_id', $rule->id)->count());
        $this->assertTrue(UserNotification::query()->where('title', 'Scoring recalculation completed')->exists());

        $this->actingAs($auditor)->post(route('scoring.rules.recalculate', $rule))->assertForbidden();
        $this->actingAs($admin)->post(route('scoring.rules.recalculate', $rule))->assertRedirect();
        $this->assertSame(2, ScoringRecalculationRun::query()->where('scoring_rule_id', $rule->id)->count());

        $lead = Lead::query()->where('lead_code', 'LD-1003')->firstOrFail();
        $this->actingAs($sales)->post(route('crm.lead-qualifications.store'), [
            'lead_id' => $lead->id,
            'status' => 'qualified',
            'quality_conditions' => [
                'budget' => 'confirmed_fit',
                'authority' => 'decision_maker',
                'need' => 'project_unit_fit',
                'timeline' => 'within_90_days',
            ],
            'decision_notes' => 'Record a new qualification and refresh the active scoring snapshot.',
        ])->assertRedirect(route('crm.lead-qualifications.index'));

        $qualification = $lead->latestQualification()->firstOrFail();
        $current = ScoreSnapshot::query()->where('scoring_rule_id', $rule->id)
            ->where('subject_type', Lead::class)->where('subject_id', $lead->id)
            ->where('is_current', true)->firstOrFail();
        $this->assertSame($qualification->id, (int) $current->metadata['qualification_id']);
        $this->actingAs($sales)->get(route('crm.lead-qualifications.index', ['lead_id' => $lead->id]))
            ->assertOk()
            ->assertSee('Rule v1')
            ->assertSee(number_format((float) $current->total_score, 2));
    }

    public function test_active_lead_scoring_rule_controls_qualification_conditions_and_snapshot(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $sales = User::query()->where('email', 'priya.nair@builder360.test')->firstOrFail();
        $lead = Lead::query()->where('lead_code', 'LD-1003')->firstOrFail();

        $this->actingAs($admin)->post(route('scoring.rules.store'), [
            'rule_key' => 'lead_quality',
            'name' => 'Lead Qualification Conditions',
            'change_reason' => 'Centralize qualification conditions under the scoring rule lifecycle.',
        ])->assertRedirect();
        $rule = ScoringRule::query()->where('rule_key', 'lead_quality')->firstOrFail();
        $this->actingAs($admin)->patch(route('scoring.rules.validate', $rule))->assertRedirect();
        $this->actingAs($admin)->patch(route('scoring.rules.submit', $rule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.approve', $rule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.activate', $rule))->assertRedirect();

        $this->actingAs($sales)->postJson(route('crm.lead-qualifications.store'), [
            'lead_id' => $lead->id,
            'status' => 'qualified',
            'quality_conditions' => [
                'budget' => 'confirmed_fit',
                'authority' => 'decision_maker',
                'need' => 'urgent_specific',
                'timeline' => 'immediate',
            ],
            'decision_notes' => 'All approved lead-quality conditions are verified for this qualification.',
        ])->assertCreated()
            ->assertJsonPath('data.score', 100)
            ->assertJsonPath('data.quality_score.rules.source', 'scoring_rule')
            ->assertJsonPath('data.quality_score.rules.version', 1);

        $snapshot = ScoreSnapshot::query()->where('scoring_rule_id', $rule->id)
            ->where('subject_type', Lead::class)->where('subject_id', $lead->id)
            ->where('is_current', true)->firstOrFail();
        $this->assertSame('100.0000', $snapshot->total_score);
        $this->assertSame('hot', $snapshot->score_band);

        $this->actingAs($sales)->get(route('crm.lead-qualifications.index'))
            ->assertOk()
            ->assertSee('Scoring Logic')
            ->assertDontSee('System Settings using key');
    }

    public function test_confirmation_adapter_uses_complete_structured_review_evidence_without_deciding_case(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $case = EmployeeConfirmationCase::query()->where('case_number', 'CNF-10001')->firstOrFail();
        $case->forceFill(['review_scores' => [
            'performance' => 5, 'behaviour' => 4, 'attendance' => 4, 'culture_fit' => 5,
            'training_completion' => 5, 'policy_compliance' => 5, 'manager_recommendation' => 4,
        ]])->save();
        $this->actingAs($admin)->post(route('scoring.rules.store'), [
            'rule_key' => 'employee_confirmation', 'name' => 'Confirmation Evidence Score',
            'change_reason' => 'Create a confirmation rule for structured source-adapter verification.',
        ])->assertRedirect();
        $rule = ScoringRule::query()->where('rule_key', 'employee_confirmation')->firstOrFail();
        $this->actingAs($admin)->patch(route('scoring.rules.validate', $rule))->assertRedirect();
        $this->actingAs($admin)->patch(route('scoring.rules.submit', $rule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.approve', $rule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.activate', $rule))->assertRedirect();

        $snapshot = ScoreSnapshot::query()->where('scoring_rule_id', $rule->id)->where('subject_id', $case->id)->firstOrFail();
        $this->assertSame('91.0000', $snapshot->total_score);
        $this->assertSame('excellent', $snapshot->score_band);
        $this->assertSame(EmployeeConfirmationCase::class, $snapshot->subject_type);
        $this->assertSame('due', $case->fresh()->status, 'Scoring must not make the authorized HR decision.');
        $this->assertNull($case->fresh()->hr_decision);

        $manager = $case->managerEmployee?->user;
        $this->assertNotNull($manager);
        $this->actingAs($manager)->patchJson(route('hr.confirmation-cases.recommend', $case), [
            'manager_recommendation' => 'confirm',
            'manager_comments' => 'All structured confirmation evidence supports a confirmation recommendation.',
            'review_scores' => [
                'performance' => 5, 'behaviour' => 4, 'attendance' => 4, 'culture_fit' => 5,
                'training_completion' => 5, 'policy_compliance' => 5, 'manager_recommendation' => 4,
            ],
        ])->assertOk();

        $current = ScoreSnapshot::query()->where('scoring_rule_id', $rule->id)
            ->where('subject_id', $case->id)->where('is_current', true)->firstOrFail();
        $this->assertSame('91.0000', $current->total_score);
        $this->assertSame('manager_recommended', $case->fresh()->status);
        $this->assertNull($case->fresh()->hr_decision, 'Scoring and recommendation must not make the HR decision.');
    }

    public function test_performance_adapter_uses_complete_component_evidence_without_closing_review(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $review = PerformanceReview::query()->where('review_number', 'PFR-10001')->firstOrFail();
        $review->forceFill(['scoring_inputs' => [
            'kpi_achievement' => 5, 'kra_achievement' => 4, 'competencies' => 4, 'behaviour' => 5,
            'attendance' => 5, 'self_review' => 4, 'manager_review' => 4, 'hr_calibration' => 4,
        ]])->save();
        $this->actingAs($admin)->post(route('scoring.rules.store'), [
            'rule_key' => 'employee_performance', 'name' => 'Performance Evidence Score',
            'change_reason' => 'Create a performance rule for structured source-adapter verification.',
        ])->assertRedirect();
        $rule = ScoringRule::query()->where('rule_key', 'employee_performance')->firstOrFail();
        $this->actingAs($admin)->patch(route('scoring.rules.validate', $rule))->assertRedirect();
        $this->actingAs($admin)->patch(route('scoring.rules.submit', $rule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.approve', $rule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.activate', $rule))->assertRedirect();

        $snapshot = ScoreSnapshot::query()->where('scoring_rule_id', $rule->id)->where('subject_id', $review->id)->firstOrFail();
        $this->assertSame('88.0000', $snapshot->total_score);
        $this->assertSame('excellent', $snapshot->score_band);
        $this->assertSame(PerformanceReview::class, $snapshot->subject_type);
        $this->assertSame('draft', $review->fresh()->status, 'Scoring must not close the authorized HR review.');
        $this->assertNull($review->fresh()->final_score);

        $manager = $review->managerEmployee?->user;
        $this->assertNotNull($manager);
        $this->actingAs($manager)->patchJson(route('hr.performance-reviews.manager-submit', $review), [
            'manager_score' => 4,
            'manager_comments' => 'Manager submitted complete performance evidence for active-rule calculation.',
            'scoring_inputs' => [
                'kpi_achievement' => 5, 'kra_achievement' => 4, 'competencies' => 4, 'behaviour' => 5,
            ],
        ])->assertOk();

        $current = ScoreSnapshot::query()->where('scoring_rule_id', $rule->id)
            ->where('subject_id', $review->id)->where('is_current', true)->firstOrFail();
        $this->assertSame('88.0000', $current->total_score);
        $this->assertSame('manager_submitted', $review->fresh()->status);
        $this->assertNull($review->fresh()->closed_at, 'Scoring and manager submission must not perform HR closure.');
    }

    public function test_recruitment_adapter_uses_weighted_panel_competencies_without_selecting_candidate(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $panel = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $interview = Interview::query()->where('interview_code', 'INT-1001')->firstOrFail();
        $originalCandidateStage = $interview->candidate->stage;
        $this->actingAs($admin)->post(route('scoring.rules.store'), [
            'rule_key' => 'recruitment_interview', 'name' => 'Interview Competency Score',
            'change_reason' => 'Create a recruitment rule for panel competency adapter verification.',
        ])->assertRedirect();
        $rule = ScoringRule::query()->where('rule_key', 'recruitment_interview')->firstOrFail();
        $this->actingAs($admin)->patch(route('scoring.rules.validate', $rule))->assertRedirect();
        $this->actingAs($admin)->patch(route('scoring.rules.submit', $rule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.approve', $rule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.activate', $rule))->assertRedirect();

        $this->actingAs($panel)->patchJson(route('recruitment.interviews.feedback', $interview), [
            'rating' => 4, 'recommendation' => 'second_round', 'panel_weight' => 2,
            'competency_scores' => [
                'role_competency' => 5, 'technical_capability' => 4, 'communication' => 4,
                'culture_fit' => 5, 'problem_solving' => 4,
            ],
        ])->assertOk();

        $snapshot = ScoreSnapshot::query()->where('scoring_rule_id', $rule->id)
            ->where('subject_id', $interview->id)->where('is_current', true)->firstOrFail();
        $this->assertSame('89.0000', $snapshot->total_score);
        $this->assertSame(Interview::class, $snapshot->subject_type);
        $this->assertSame('scheduled', $interview->fresh()->status);
        $this->assertSame($originalCandidateStage, $interview->fresh()->candidate->stage);
        $this->actingAs($panel)->get(route('recruitment.interviews.index'))
            ->assertOk()
            ->assertSee('89.00 / 100')
            ->assertSee('Rule v1');
    }

    public function test_vendor_adapter_uses_complete_scorecard_evidence_without_changing_vendor_status(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $construction = User::query()->where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $vendor = Vendor::query()->where('status', 'active')->firstOrFail();
        $vendor->forceFill(['scoring_inputs' => [
            'acceptance_rate' => 100, 'quality' => 80, 'on_time_delivery' => 90, 'fulfillment' => 70,
            'price_competitiveness' => 60, 'documentation' => 100, 'responsiveness' => 80, 'issue_resolution' => 90,
        ]])->save();
        $this->actingAs($admin)->post(route('scoring.rules.store'), [
            'rule_key' => 'vendor_performance', 'name' => 'Vendor Evidence Score',
            'change_reason' => 'Create a vendor rule for complete scorecard adapter verification.',
        ])->assertRedirect();
        $rule = ScoringRule::query()->where('rule_key', 'vendor_performance')->firstOrFail();
        $this->actingAs($admin)->patch(route('scoring.rules.validate', $rule))->assertRedirect();
        $this->actingAs($admin)->patch(route('scoring.rules.submit', $rule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.approve', $rule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.activate', $rule))->assertRedirect();

        $this->actingAs($construction)->patch(route('procurement.vendors.performance-score.update', $vendor), [
            'acceptance_rate' => 100, 'quality' => 80, 'on_time_delivery' => 90, 'fulfillment' => 70,
            'price_competitiveness' => 60, 'documentation' => 100, 'responsiveness' => 80, 'issue_resolution' => 90,
        ])->assertRedirect(route('procurement.vendors.index'));

        $snapshot = ScoreSnapshot::query()->where('scoring_rule_id', $rule->id)
            ->where('subject_id', $vendor->id)->where('is_current', true)->firstOrFail();
        $this->assertSame('85.0000', $snapshot->total_score);
        $this->assertSame(Vendor::class, $snapshot->subject_type);
        $this->assertSame('active', $vendor->fresh()->status);
        $this->actingAs($construction)->get(route('procurement.vendors.index'))
            ->assertOk()
            ->assertSee('Performance: 85.00 / 100')
            ->assertSee('Performance evidence')
            ->assertSee('Calculate vendor score');
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'procurement.vendor.performance_scored',
            'auditable_type' => Vendor::class,
            'auditable_id' => $vendor->id,
        ]);
    }

    public function test_project_adapter_uses_complete_health_evidence_without_changing_project_status(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $project = Project::query()->where('status', 'active')->firstOrFail();
        $project->forceFill(['scoring_inputs' => [
            'construction_progress' => 80, 'sales_progress' => 70, 'collection_progress' => 60,
            'budget_control' => 90, 'schedule_variance' => 50, 'inventory_health' => 80,
            'approval_delays' => 100, 'procurement_delays' => 90, 'receivables' => 70,
        ]])->save();
        $this->actingAs($admin)->post(route('scoring.rules.store'), [
            'rule_key' => 'project_health', 'name' => 'Project Health Evidence Score',
            'change_reason' => 'Create a project rule for complete health evidence adapter verification.',
        ])->assertRedirect();
        $rule = ScoringRule::query()->where('rule_key', 'project_health')->firstOrFail();
        $this->actingAs($admin)->patch(route('scoring.rules.validate', $rule))->assertRedirect();
        $this->actingAs($admin)->patch(route('scoring.rules.submit', $rule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.approve', $rule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.activate', $rule))->assertRedirect();

        $this->actingAs($admin)->patch(route('projects.health-score.update', $project), [
            'construction_progress' => 80, 'sales_progress' => 70, 'collection_progress' => 60,
            'budget_control' => 90, 'schedule_variance' => 50, 'inventory_health' => 80,
            'approval_delays' => 100, 'procurement_delays' => 90, 'receivables' => 70,
        ])->assertRedirect(route('projects.index'));

        $snapshot = ScoreSnapshot::query()->where('scoring_rule_id', $rule->id)
            ->where('subject_id', $project->id)->where('is_current', true)->firstOrFail();
        $this->assertSame('77.5000', $snapshot->total_score);
        $this->assertSame('good', $snapshot->score_band);
        $this->assertSame(Project::class, $snapshot->subject_type);
        $this->assertSame('active', $project->fresh()->status);

        $dashboardProject = collect(app(Builder360Bootstrap::class)->forUser($director)['dashboard']['projects'])
            ->firstWhere('db_id', $project->id);
        $this->assertNotNull($dashboardProject);
        $this->assertSame(77.5, $dashboardProject['health']);
        $this->assertSame('good', $dashboardProject['health_band']);
        $this->assertSame(1, $dashboardProject['health_rule_version']);

        $export = $this->actingAs($admin)->get(route('projects.cost-roi.export', [
            'project_id' => $project->id,
            'format' => 'csv',
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('health_score', $export);
        $this->assertStringContainsString(',77.50,', $export);

        $this->actingAs($admin)->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Health: 77.50 / 100')
            ->assertSee('Rule v1')
            ->assertSee('Health evidence')
            ->assertSee('Calculate health score');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'projects.health_score.updated',
            'auditable_type' => Project::class,
            'auditable_id' => $project->id,
        ]);
    }

    public function test_customer_satisfaction_adapter_scores_project_summary_without_rewriting_rating(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $buyer = User::query()->where('email', 'rohan.shah@example.test')->firstOrFail();
        $ticket = ServiceTicket::query()->whereNotNull('project_id')->firstOrFail();
        $ticket->forceFill(['customer_rating' => 4, 'scoring_inputs' => [
            'resolution_time' => 90, 'reopened_penalty' => 100, 'escalation_impact' => 80,
        ]])->save();
        $this->actingAs($admin)->post(route('scoring.rules.store'), [
            'rule_key' => 'customer_satisfaction', 'name' => 'Project Satisfaction Summary',
            'change_reason' => 'Create a customer satisfaction rule for project summary adapter verification.',
        ])->assertRedirect();
        $rule = ScoringRule::query()->where('rule_key', 'customer_satisfaction')->firstOrFail();
        $update = $this->structuredUpdatePayload($rule);
        $update['minimum_sample_size'] = 1;
        $this->actingAs($admin)->patch(route('scoring.rules.update', $rule), $update)->assertRedirect();
        $refreshedRule = $rule->fresh();
        $this->assertSame(
            $refreshedRule->configuration_checksum,
            app(\App\Domain\Scoring\Services\ScoringRuleIntegrityService::class)->expectedChecksum($refreshedRule),
        );
        $this->actingAs($admin)->patch(route('scoring.rules.validate', $rule))->assertRedirect()->assertSessionDoesntHaveErrors();
        $this->actingAs($admin)->patch(route('scoring.rules.submit', $rule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.approve', $rule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.activate', $rule))->assertRedirect();

        $snapshot = ScoreSnapshot::query()->where('scoring_rule_id', $rule->id)->where('subject_id', $ticket->project_id)->firstOrFail();
        $this->assertSame('86.0000', $snapshot->total_score);
        $this->assertSame(Project::class, $snapshot->subject_type);
        $this->assertSame(4, $ticket->fresh()->customer_rating);
        $this->assertSame(1, $snapshot->metadata['sample_size']);

        $ticket->forceFill(['status' => 'resolved', 'resolved_at' => now()])->save();
        $this->actingAs($buyer)->patch(route('buyer.service-tickets.close', $ticket), [
            'customer_rating' => 4,
            'note' => 'Close the resolved ticket with verified satisfaction evidence.',
            'scoring_inputs' => [
                'resolution_time' => 90, 'reopened_penalty' => 100, 'escalation_impact' => 80,
            ],
        ])->assertRedirect(route('buyer.summary'));

        $current = ScoreSnapshot::query()->where('scoring_rule_id', $rule->id)
            ->where('subject_id', $ticket->project_id)->where('is_current', true)->firstOrFail();
        $this->assertSame('86.0000', $current->total_score);
        $this->assertSame(4, $ticket->fresh()->customer_rating, 'Aggregate recalculation must preserve the individual rating.');
        $this->assertSame('closed', $ticket->fresh()->status);
    }

    public function test_exit_feedback_adapter_scores_department_summary_without_rewriting_responses(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $interview = EmployeeExitInterview::query()->whereNotNull('submitted_at')->firstOrFail();
        $interview->forceFill(['scoring_inputs' => [
            'career_growth' => 4, 'work_environment' => 5, 'rehire_recommendation' => 5,
        ]])->save();
        $originalResponses = $interview->confidential_responses;
        $this->actingAs($admin)->post(route('scoring.rules.store'), [
            'rule_key' => 'exit_feedback', 'name' => 'Department Exit Feedback Summary',
            'change_reason' => 'Create an exit feedback rule for department summary adapter verification.',
        ])->assertRedirect();
        $rule = ScoringRule::query()->where('rule_key', 'exit_feedback')->firstOrFail();
        $update = $this->structuredUpdatePayload($rule);
        $update['minimum_sample_size'] = 1;
        $this->actingAs($admin)->patch(route('scoring.rules.update', $rule), $update)->assertRedirect();
        $this->actingAs($admin)->patch(route('scoring.rules.validate', $rule))->assertRedirect();
        $this->actingAs($admin)->patch(route('scoring.rules.submit', $rule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.approve', $rule))->assertRedirect();
        $this->actingAs($director)->patch(route('scoring.rules.activate', $rule))->assertRedirect();

        $subject = ScoringAggregateSubject::query()->where('subject_type', 'exit_feedback_department')->firstOrFail();
        $snapshot = ScoreSnapshot::query()->where('scoring_rule_id', $rule->id)->where('subject_id', $subject->id)->firstOrFail();
        $this->assertSame('80.0000', $snapshot->total_score);
        $this->assertSame(ScoringAggregateSubject::class, $snapshot->subject_type);
        $this->assertSame(1, $snapshot->metadata['sample_size']);
        $this->assertSame($originalResponses, $interview->fresh()->confidential_responses);
        $this->assertSame(4, $interview->fresh()->overall_experience_rating);

        $employeeUser = $interview->employee?->user;
        $this->assertNotNull($employeeUser);
        $interview->forceFill(['status' => 'scheduled', 'submitted_at' => null])->save();
        $submittedResponses = [
            'primary_reason' => 'Career progression and role breadth.',
            'manager_feedback' => 'Manager relationship remained constructive.',
        ];
        $this->actingAs($employeeUser)->patchJson(route('hr.exit-interviews.submit', $interview), [
            'separation_reason' => 'career_growth',
            'rehire_recommendation' => 'yes',
            'overall_experience_rating' => 4,
            'manager_relationship_rating' => 4,
            'workload_rating' => 4,
            'compensation_rating' => 4,
            'confidential_responses' => $submittedResponses,
            'scoring_inputs' => [
                'career_growth' => 4, 'work_environment' => 5, 'rehire_recommendation' => 5,
            ],
        ])->assertOk();

        $current = ScoreSnapshot::query()->where('scoring_rule_id', $rule->id)
            ->where('subject_id', $subject->id)->where('is_current', true)->firstOrFail();
        $this->assertSame('85.0000', $current->total_score);
        $this->assertSame($submittedResponses, $interview->fresh()->confidential_responses);
        $this->assertSame('submitted', $interview->fresh()->status);
    }

    /** @return array<string, mixed> */
    private function structuredUpdatePayload(ScoringRule $rule): array
    {
        $configuration = $rule->configuration;

        return [
            'name' => $rule->name,
            'change_reason' => 'Update the structured scoring controls with documented business approval.',
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
            'override_allowed' => $configuration['override']['allowed'] ? 1 : 0,
            'override_reason_required' => $configuration['override']['reason_required'] ? 1 : 0,
        ];
    }
}
