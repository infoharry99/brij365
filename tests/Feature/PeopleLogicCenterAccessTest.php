<?php

namespace Tests\Feature;

use App\Domain\Hr\Services\AttendanceRosterRulePackValidator;
use App\Domain\Scoring\Services\LogicCenterRegister;
use App\Domain\Scoring\Services\ScoringRuleCatalog;
use App\Domain\Scoring\Services\ScoringConfigurationChecksum;
use App\Domain\Scoring\Services\LogicCenterAccessService;
use App\Models\Role;
use App\Models\ScoringRule;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PeopleLogicCenterAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_administrator_can_open_every_governed_logic_center_section(): void
    {
        $this->seed();
        $administrator = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($administrator)->get(route('scoring.index'));

        $response->assertOk()
            ->assertSee('Logic Center Overview')
            ->assertSee('Business Scoring')
            ->assertSee('Employee Performance')
            ->assertSee('Statutory &amp; Payroll Rules', false)
            ->assertSee('Attendance &amp; Roster Rules', false)
            ->assertSee('Simulation &amp; Impact', false)
            ->assertSee('Versions, Recalculation &amp; Audit', false);
    }

    public function test_compliance_role_can_review_statutory_rules_but_cannot_open_roster_governance(): void
    {
        $this->seed();
        $complianceOfficer = User::query()->where('email', 'meera.kapoor@builder360.test')->firstOrFail();

        $this->actingAs($complianceOfficer)
            ->get(route('scoring.index', ['view' => 'statutory']))
            ->assertOk()
            ->assertSee('Statutory &amp; Payroll Rules', false)
            ->assertSee('Governed variable packs');

        $this->actingAs($complianceOfficer)
            ->get(route('scoring.index', ['view' => 'roster']))
            ->assertForbidden();
    }

    public function test_payroll_role_can_simulate_statutory_rules_without_performance_rule_access(): void
    {
        $this->seed();
        $payrollAdministrator = User::query()->where('email', 'kavita.shah@builder360.test')->firstOrFail();

        $this->actingAs($payrollAdministrator)
            ->get(route('scoring.index', ['view' => 'simulation']))
            ->assertOk()
            ->assertSee('Statutory payroll impact');

        $this->actingAs($payrollAdministrator)
            ->get(route('scoring.index', ['view' => 'performance']))
            ->assertForbidden();
    }

    public function test_payroll_role_cannot_open_business_scoring_and_audit_does_not_leak_business_rules(): void
    {
        $this->seed();
        $payrollAdministrator = User::query()->where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $configuration = app(ScoringRuleCatalog::class)->defaultConfiguration('lead_quality');
        $ruleName = 'Restricted business scoring fixture';

        ScoringRule::query()->create([
            'company_id' => $payrollAdministrator->company_id,
            'created_by_user_id' => User::query()->where('email', 'nikhil.desai@builder360.test')->value('id'),
            'rule_key' => 'lead_quality',
            'name' => $ruleName,
            'version' => 1,
            'status' => 'active',
            'configuration' => $configuration,
            'configuration_checksum' => app(ScoringConfigurationChecksum::class)->make($configuration),
            'change_reason' => 'Prove payroll statutory access does not grant business scoring visibility.',
            'activated_at' => now(),
        ]);

        $this->actingAs($payrollAdministrator)
            ->get(route('scoring.index', ['view' => 'business']))
            ->assertForbidden();

        $this->actingAs($payrollAdministrator)
            ->get(route('scoring.index', ['view' => 'audit']))
            ->assertOk()
            ->assertSee('Versions, Recalculation &amp; Audit', false)
            ->assertDontSee($ruleName);
    }

    public function test_logic_center_permission_migration_rollback_is_non_destructive(): void
    {
        $this->seed();

        $role = Role::query()->where('slug', 'payroll')->firstOrFail();
        $permissions = array_values(array_unique([
            ...($role->permissions ?? []),
            'scoring.view',
            'custom.permission.that.predates.logic.center',
        ]));

        $role->forceFill(['permissions' => $permissions])->save();

        $migration = require database_path('migrations/2026_07_18_000100_add_people_logic_center_permissions.php');
        $migration->down();

        $this->assertSame($permissions, $role->fresh()->permissions);
    }

    public function test_employee_swap_permission_does_not_expose_the_administrative_logic_center(): void
    {
        $this->seed();
        $employee = User::query()->where('email', 'amit.verma@builder360.test')->firstOrFail();

        $this->actingAs($employee)->get(route('scoring.index'))->assertForbidden();
    }

    public function test_generic_scoring_view_does_not_grant_logic_center_audit_access(): void
    {
        $this->seed();
        $companyId = User::query()->where('email', 'nikhil.desai@builder360.test')->value('company_id');
        $role = Role::query()->create([
            'slug' => 'scoring_view_only',
            'name' => 'Scoring View Only',
            'scope_level' => 'readonly',
            'permissions' => ['scoring.view'],
            'is_active' => true,
        ]);
        $viewer = User::factory()->create([
            'role_id' => $role->id,
            'company_id' => $companyId,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->get(route('scoring.index', ['view' => 'business']))
            ->assertOk();

        $this->actingAs($viewer)
            ->get(route('scoring.index', ['view' => 'audit']))
            ->assertForbidden();

        $this->assertFalse(app(LogicCenterAccessService::class)->capabilities($viewer)['viewAudit']);
    }

    public function test_legacy_verified_flag_is_not_treated_as_independent_statutory_attestation(): void
    {
        $this->seed();
        $administrator = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $setting = SystemSetting::query()->create([
            'company_id' => $administrator->company_id,
            'created_by_user_id' => $administrator->id,
            'scope_key' => 'company',
            'setting_group' => 'statutory',
            'setting_key' => 'hr.statutory.pf',
            'label' => 'Legacy metadata verification must be ignored',
            'description' => 'Regression fixture.',
            'value_type' => 'json',
            'value' => ['verified' => true],
            'status' => 'draft',
            'version' => 99,
            'effective_from' => now()->toDateString(),
            'workflow_history' => [],
            'metadata' => ['official_source' => ['verified' => true]],
        ]);

        $row = collect(app(LogicCenterRegister::class)->variablePacks($administrator, 'statutory'))
            ->firstWhere('id', $setting->id);

        $this->assertNotNull($row);
        $this->assertTrue($row->requiresVerification);
        $this->assertFalse($row->verified);
        $this->assertSame('Not verified', $row->sourceAuthority);
    }

    public function test_business_scoring_viewer_does_not_receive_statutory_or_roster_pack_metadata(): void
    {
        $this->seed();
        $businessScoringViewer = User::query()->where('email', 'priya.nair@builder360.test')->firstOrFail();
        $administrator = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $setting = SystemSetting::query()->create([
            'company_id' => $administrator->company_id,
            'created_by_user_id' => $administrator->id,
            'scope_key' => 'company',
            'setting_group' => 'statutory',
            'setting_key' => 'hr.statutory.pf',
            'label' => 'Restricted statutory metadata fixture',
            'description' => 'Regression fixture.',
            'value_type' => 'json',
            'value' => ['governed_statutory_pack_version' => 1],
            'status' => 'draft',
            'version' => 101,
            'effective_from' => now()->toDateString(),
            'workflow_history' => [],
            'metadata' => [],
        ]);

        $packs = collect(app(LogicCenterRegister::class)->variablePacks($businessScoringViewer, 'overview'));

        $this->assertFalse($packs->contains(fn ($pack): bool => $pack->id === $setting->id));
        $this->assertSame([
            'variablePacks' => 0,
            'activePacks' => 0,
            'unverifiedPacks' => 0,
            'draftPacks' => 0,
        ], app(LogicCenterRegister::class)->readiness($businessScoringViewer));
    }

    public function test_roster_logic_center_exposes_normalized_governed_operational_variables(): void
    {
        $this->seed();
        $administrator = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $setting = SystemSetting::query()->create([
            'company_id' => $administrator->company_id,
            'created_by_user_id' => $administrator->id,
            'scope_key' => 'company:'.$administrator->company_id,
            'setting_group' => 'hr',
            'setting_key' => AttendanceRosterRulePackValidator::ROSTER_KEY,
            'label' => 'Governed roster operating limits',
            'description' => 'Logic Center regression fixture.',
            'value_type' => 'object',
            'value' => [
                'publication_lead_days' => 7,
                'swap_request_cutoff_hours' => 48,
                'maximum_rotation_generation_horizon_days' => 120,
                'roster_reopen_limit_days' => 30,
                'attendance_reopen_limit_days' => 45,
            ],
            'status' => 'draft',
            'version' => 1,
            'effective_from' => now()->toDateString(),
            'workflow_history' => [],
            'metadata' => ['fixture' => 'logic_center_roster_variables'],
        ]);

        $row = collect(app(LogicCenterRegister::class)->variablePacks($administrator, 'roster'))
            ->firstWhere('id', $setting->id);

        $this->assertNotNull($row);
        $variables = collect($row->variables)->keyBy('key');
        $this->assertSame('7 days', $variables->get('publication_lead_days')['value']);
        $this->assertSame('48 hr', $variables->get('swap_request_cutoff_hours')['value']);
        $this->assertSame('120 days', $variables->get('maximum_rotation_generation_horizon_days')['value']);
        $this->assertSame('30 days', $variables->get('roster_reopen_limit_days')['value']);
        $this->assertSame('45 days', $variables->get('attendance_reopen_limit_days')['value']);

        $hrManager = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $this->actingAs($hrManager)
            ->get(route('scoring.index', ['view' => 'roster']))
            ->assertOk()
            ->assertSee('Publication Lead Days')
            ->assertSee('7 days')
            ->assertSee('Swap Request Cutoff Hours')
            ->assertSee('48 hr')
            ->assertSee('Create attendance calculation draft')
            ->assertSee('Create roster governance draft')
            ->assertDontSee('Attendance notification rules');
    }

    public function test_logic_center_creates_a_typed_company_scoped_roster_draft(): void
    {
        $this->seed();
        $administrator = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $this->actingAs($administrator)
            ->post(route('scoring.attendance-roster-rule-packs.store'), [
                'setting_key' => AttendanceRosterRulePackValidator::ROSTER_KEY,
                'label' => 'July roster operating limits',
                'description' => 'Prepare a governed roster limit change for independent approval.',
                'effective_from' => now()->addDay()->toDateString(),
                'value' => [
                    'company_timezone' => 'Asia/Kolkata',
                    'block_shift_overlaps' => '1',
                    'minimum_rest_minutes' => '660',
                    'maximum_consecutive_workdays' => '6',
                    'require_complete_period_assignment' => '1',
                    'coverage_scope' => 'all_active_employees',
                    'publication_lead_days' => '7',
                    'swap_request_cutoff_hours' => '48',
                    'maximum_rotation_generation_horizon_days' => '120',
                    'roster_reopen_limit_days' => '30',
                    'attendance_reopen_limit_days' => '45',
                ],
            ])
            ->assertRedirect(route('scoring.index', ['view' => 'roster']))
            ->assertSessionHas('status');

        $draft = SystemSetting::query()
            ->where('company_id', $administrator->company_id)
            ->where('setting_key', AttendanceRosterRulePackValidator::ROSTER_KEY)
            ->where('label', 'July roster operating limits')
            ->firstOrFail();

        $this->assertSame('draft', $draft->status);
        $this->assertSame('company:'.$administrator->company_id, $draft->scope_key);
        $this->assertTrue($draft->value['block_shift_overlaps']);
        $this->assertTrue($draft->value['require_complete_period_assignment']);
        $this->assertSame(660, $draft->value['minimum_rest_minutes']);
        $this->assertSame('all_active_employees', $draft->value['coverage_scope']);
        $this->assertSame('people_logic_center', $draft->metadata['source']);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'settings.system_setting.draft_created',
            'user_id' => $administrator->id,
        ]);
    }

    public function test_attendance_pack_thresholds_are_validated_in_logic_center_and_generic_settings_routes(): void
    {
        $this->seed();
        $administrator = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $hrManager = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $invalidValue = [
            'company_timezone' => 'Asia/Kolkata',
            'late_grace_minutes' => 10,
            'early_leave_grace_minutes' => 10,
            'half_day_threshold_minutes' => 480,
            'full_day_threshold_minutes' => 240,
            'rounding' => 'nearest_minute',
        ];

        $this->actingAs($hrManager)
            ->from(route('scoring.index', ['view' => 'roster']))
            ->post(route('scoring.attendance-roster-rule-packs.store'), [
                'setting_key' => AttendanceRosterRulePackValidator::ATTENDANCE_KEY,
                'label' => 'Invalid attendance thresholds',
                'effective_from' => now()->toDateString(),
                'value' => $invalidValue,
            ])
            ->assertRedirect(route('scoring.index', ['view' => 'roster']))
            ->assertSessionHasErrors('value.full_day_threshold_minutes');

        $this->actingAs($administrator)
            ->postJson(route('settings.system-settings.store'), [
                'company_id' => $administrator->company_id,
                'setting_group' => 'hr',
                'setting_key' => AttendanceRosterRulePackValidator::ATTENDANCE_KEY,
                'label' => 'Invalid generic attendance thresholds',
                'value_type' => 'object',
                'value' => $invalidValue,
                'effective_from' => now()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('value.full_day_threshold_minutes');

        $this->assertDatabaseMissing('system_settings', [
            'label' => 'Invalid attendance thresholds',
        ]);
        $this->assertDatabaseMissing('system_settings', [
            'label' => 'Invalid generic attendance thresholds',
        ]);
    }

    public function test_approval_revalidates_and_canonicalizes_legacy_attendance_pack_drafts(): void
    {
        $this->seed();
        $administrator = User::query()->where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::query()->where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $attendanceVersion = (int) SystemSetting::query()
            ->where('scope_key', 'company:'.$administrator->company_id)
            ->where('setting_key', AttendanceRosterRulePackValidator::ATTENDANCE_KEY)
            ->max('version') + 1;

        $draft = SystemSetting::query()->create([
            'company_id' => $administrator->company_id,
            'created_by_user_id' => $administrator->id,
            'scope_key' => 'company:'.$administrator->company_id,
            'setting_group' => 'hr',
            'setting_key' => AttendanceRosterRulePackValidator::ATTENDANCE_KEY,
            'label' => 'Legacy attendance pack alias',
            'description' => 'A legacy draft that predates canonical company_timezone storage.',
            'value_type' => 'object',
            'value' => [
                'timezone' => 'Asia/Kolkata',
                'late_grace_minutes' => '15',
                'early_leave_grace_minutes' => '5',
                'half_day_threshold_minutes' => '240',
                'full_day_threshold_minutes' => '480',
                'rounding' => 'floor_minute',
            ],
            'status' => 'draft',
            'version' => $attendanceVersion,
            'effective_from' => now()->toDateString(),
            'workflow_history' => [],
            'metadata' => ['fixture' => 'legacy_pack'],
        ]);

        $this->actingAs($director)
            ->patchJson(route('settings.system-settings.approve', $draft), [
                'note' => 'Independently reviewed and approved.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $approved = $draft->fresh();
        $this->assertSame('Asia/Kolkata', $approved->value['company_timezone']);
        $this->assertArrayNotHasKey('timezone', $approved->value);
        $this->assertSame(15, $approved->value['late_grace_minutes']);
        $this->assertSame('floor_minute', $approved->value['rounding']);

        $rosterVersion = (int) SystemSetting::query()
            ->where('scope_key', 'company:'.$administrator->company_id)
            ->where('setting_key', AttendanceRosterRulePackValidator::ROSTER_KEY)
            ->max('version') + 1;

        $malformed = SystemSetting::query()->create([
            'company_id' => $administrator->company_id,
            'created_by_user_id' => $administrator->id,
            'scope_key' => 'company:'.$administrator->company_id,
            'setting_group' => 'hr',
            'setting_key' => AttendanceRosterRulePackValidator::ROSTER_KEY,
            'label' => 'Malformed legacy roster pack',
            'description' => 'Must fail closed during approval.',
            'value_type' => 'object',
            'value' => ['minimum_rest_minutes' => -1],
            'status' => 'draft',
            'version' => $rosterVersion,
            'effective_from' => now()->toDateString(),
            'workflow_history' => [],
            'metadata' => ['fixture' => 'malformed_pack'],
        ]);

        $this->actingAs($director)
            ->patchJson(route('settings.system-settings.approve', $malformed), [
                'note' => 'This must not activate.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('value.minimum_rest_minutes');

        $this->assertSame('draft', $malformed->fresh()->status);
    }
}
