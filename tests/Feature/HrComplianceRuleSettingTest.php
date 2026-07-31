<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrComplianceRuleSettingTest extends TestCase
{
    public function test_compliance_rule_register_renders_as_blade_workspace_for_browser_requests(): void
    {
        $this->seed();
        $user = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $this->actingAs($user)->get(route('hr.compliance-rule-settings.index'))->assertOk()->assertViewIs('hr.compliance.index')->assertSee('Compliance Rules');
    }

    public function test_compliance_officer_can_create_rule_draft_from_blade_form(): void
    {
        $this->seed();
        $user = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();

        $this->actingAs($user)->post(route('hr.compliance-rule-settings.store'), [
            'company_id' => $company->id,
            'setting_key' => 'hr.statutory.esic',
            'label' => 'ESIC Rule Pack Browser Test',
            'effective_from' => now()->addDay()->toDateString(),
            'value' => [
                'approval_chain' => ['HR / Compliance approval'],
                'statutory_validation_required' => '1',
                'applicability' => 'Configured workforce',
                'wage_basis' => 'Configured eligible wage',
                'calculation_method' => 'Approved statutory method',
                'rates' => ['default' => '0'],
            ],
        ])->assertRedirect(route('hr.compliance-rule-settings.index'));

        $this->assertDatabaseHas('system_settings', ['setting_key' => 'hr.statutory.esic', 'label' => 'ESIC Rule Pack Browser Test', 'status' => 'draft']);
    }

    use RefreshDatabase;

    public function test_compliance_officer_can_list_create_and_separate_approver_can_activate_rule_pack(): void
    {
        $this->seed();

        $compliance = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();

        $this->actingAs($compliance)
            ->getJson(route('hr.compliance-rule-settings.index'))
            ->assertOk()
            ->assertJsonFragment(['setting_key' => 'payroll.tax_rules'])
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'setting_key', 'value', 'status', 'version', 'workflow_history'],
                ],
            ]);

        $draftId = $this->actingAs($compliance)
            ->postJson(route('hr.compliance-rule-settings.store'), [
                'company_id' => $company->id,
                'setting_key' => 'hr.statutory.pf',
                'label' => 'Provident Fund Rule Pack',
                'description' => 'PF applicability, wage basis and contribution controls verified for governed approval.',
                'effective_from' => now()->addDay()->toDateString(),
                'value' => [
                    'applicability' => ['employee_categories' => ['full_time'], 'states' => ['MH']],
                    'wage_basis' => 'basic_plus_da',
                    'calculation_method' => 'percentage_with_ceiling',
                    'rates' => ['employee' => 12, 'employer' => 12, 'ceiling' => 15000],
                    'rounding' => 'nearest_rupee',
                    'verified' => true,
                    'statutory_validation_required' => true,
                    'approval_chain' => ['hr_preparer', 'compliance_approver'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.setting_key', 'hr.statutory.pf')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.value.rates.employee', 12)
            ->json('data.id');

        $draft = SystemSetting::findOrFail($draftId);

        $this->actingAs($compliance)
            ->patchJson(route('hr.compliance-rule-settings.approve', $draft), [
                'note' => 'Self approval must be blocked.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('setting');

        $this->actingAs($director)
            ->patchJson(route('hr.compliance-rule-settings.approve', $draft), [
                'note' => 'Approved after statutory owner review.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.approved_by.email', 'aditya.mehra@builder360.test');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'settings.system_setting.draft_created',
            'user_id' => $compliance->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'settings.system_setting.approved',
            'user_id' => $director->id,
        ]);
    }

    public function test_statutory_rule_cannot_be_approved_until_its_payload_is_verified(): void
    {
        $this->seed();

        $compliance = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();

        $draftId = $this->actingAs($compliance)
            ->postJson(route('hr.compliance-rule-settings.store'), [
                'company_id' => $company->id,
                'setting_key' => 'hr.statutory.esic',
                'label' => 'Unverified ESIC Rule Pack',
                'effective_from' => now()->addDay()->toDateString(),
                'value' => [
                    'applicability' => 'Configured workforce',
                    'wage_basis' => 'Configured eligible wage',
                    'calculation_method' => 'Approved statutory method',
                    'rates' => ['default' => 0],
                    'verified' => false,
                    'statutory_validation_required' => true,
                    'approval_chain' => ['hr_preparer', 'compliance_approver'],
                ],
            ])
            ->assertCreated()
            ->json('data.id');

        $draft = SystemSetting::findOrFail($draftId);

        $this->actingAs($director)
            ->patchJson(route('hr.compliance-rule-settings.approve', $draft), [
                'note' => 'This draft must remain pending expert verification.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('setting')
            ->assertJsonPath(
                'errors.setting.0',
                'Statutory validation must be verified before this compliance rule can be approved.',
            );

        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertDatabaseMissing('audit_events', [
            'event_type' => 'settings.system_setting.approved',
            'auditable_id' => $draft->id,
        ]);
    }

    public function test_compliance_rule_setting_rejects_invalid_statutory_payload_and_unapproved_keys(): void
    {
        $this->seed();

        $compliance = User::where('email', 'meera.kapoor@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();

        $this->actingAs($compliance)
            ->postJson(route('hr.compliance-rule-settings.store'), [
                'company_id' => $company->id,
                'setting_key' => 'hr.statutory.pf',
                'label' => 'Invalid PF Rule Pack',
                'effective_from' => now()->toDateString(),
                'value' => [
                    'applicability' => ['states' => ['MH']],
                    'verified' => false,
                    'statutory_validation_required' => true,
                    'approval_chain' => [],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'value.approval_chain',
                'value.wage_basis',
                'value.calculation_method',
                'value.rates',
            ]);

        $this->actingAs($compliance)
            ->postJson(route('hr.compliance-rule-settings.store'), [
                'company_id' => $company->id,
                'setting_key' => 'workflow.approval_chains',
                'label' => 'Out of Scope Workflow',
                'effective_from' => now()->toDateString(),
                'value' => [
                    'statutory_validation_required' => true,
                    'approval_chain' => ['invalid'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('setting_key');
    }

    public function test_partner_cannot_access_compliance_rule_settings(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $this->actingAs($partner)
            ->getJson(route('hr.compliance-rule-settings.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('hr.compliance-rule-settings.store'), [])
            ->assertForbidden();
    }

    public function test_wildcard_director_cannot_list_create_or_approve_compliance_rules_outside_active_company(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $activeCompany = Company::where('code', 'B360D')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();

        $this->assertTrue($director->hasPermission('*'));

        $otherCompanyDraft = SystemSetting::create([
            'company_id' => $otherCompany->id,
            'created_by_user_id' => $admin->id,
            'scope_key' => 'company:'.$otherCompany->id,
            'setting_group' => 'hr',
            'setting_key' => 'hr.statutory.pf',
            'label' => 'Other Company PF Rule Pack',
            'value_type' => 'object',
            'value' => [
                'approval_chain' => ['compliance_approver'],
                'statutory_validation_required' => true,
            ],
            'status' => 'draft',
            'version' => 1,
            'workflow_history' => [],
            'metadata' => ['source' => 'test'],
        ]);

        $this->actingAs($director)
            ->getJson(route('hr.compliance-rule-settings.index', ['company_id' => $otherCompany->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company_id');

        $this->actingAs($director)
            ->getJson(route('hr.compliance-rule-settings.index'))
            ->assertOk()
            ->assertJsonMissing(['label' => 'Other Company PF Rule Pack']);

        $this->actingAs($director)
            ->postJson(route('hr.compliance-rule-settings.store'), [
                'company_id' => $otherCompany->id,
                'setting_key' => 'hr.statutory.esic',
                'label' => 'Cross-Company ESIC Rule Pack',
                'effective_from' => now()->addDay()->toDateString(),
                'value' => [
                    'approval_chain' => ['compliance_approver'],
                    'statutory_validation_required' => true,
                    'applicability' => 'Configured workforce',
                    'wage_basis' => 'Configured eligible wage',
                    'calculation_method' => 'Approved statutory method',
                    'rates' => ['default' => 0],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company_id');

        $this->actingAs($director)
            ->patchJson(route('hr.compliance-rule-settings.approve', $otherCompanyDraft), [
                'note' => 'Must remain inside the active operating company.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('system_settings', [
            'company_id' => $otherCompany->id,
            'label' => 'Cross-Company ESIC Rule Pack',
        ]);
        $this->assertSame('draft', $otherCompanyDraft->fresh()->status);
        $this->assertSame($activeCompany->id, app(CompanyScopeService::class)->companyIdFor($director));
    }
}
