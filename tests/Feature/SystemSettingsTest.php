<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Settings\SystemSettingResolver;
use App\Services\Settings\SystemSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_can_list_seeded_active_settings(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $this->actingAs($admin)
            ->getJson(route('settings.system-settings.index', ['status' => 'active']))
            ->assertOk()
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['setting_group', 'setting_key', 'value', 'status', 'version', 'company', 'approved_by'],
                ],
            ]);
    }

    public function test_admin_can_use_native_blade_system_settings_workspace(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('settings.system-settings.index'))
            ->assertOk()
            ->assertSee('Workspace for configurable business rules')
            ->assertSee('Create setting draft')
            ->assertSee('Setting filters')
            ->assertSee('Setting register')
            ->assertSee('name="value"', false)
            ->assertSee('after_sales.sla_hours')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);

        $this->actingAs($admin)
            ->post(route('settings.system-settings.store'), [
                'company_id' => $company->id,
                'setting_group' => 'after_sales',
                'setting_key' => 'after_sales.sla_hours',
                'label' => 'Blade After-Sales SLA Hours',
                'description' => 'Created through the native Blade settings workspace.',
                'value_type' => 'object',
                'value' => json_encode([
                    'low' => 96,
                    'medium' => 48,
                    'high' => 18,
                    'critical' => 6,
                ]),
                'effective_from' => now()->addDay()->toDateString(),
            ])
            ->assertRedirect(route('settings.system-settings.index'))
            ->assertSessionHas('status');

        $draft = SystemSetting::where('setting_key', 'after_sales.sla_hours')
            ->where('label', 'Blade After-Sales SLA Hours')
            ->firstOrFail();

        $this->assertSame('draft', $draft->status);
        $this->assertSame(6, $draft->value['critical']);

        $this->actingAs($director)
            ->patch(route('settings.system-settings.approve', $draft), [
                'note' => 'Approved through native Blade settings workspace.',
            ])
            ->assertRedirect(route('settings.system-settings.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('system_settings', [
            'id' => $draft->id,
            'status' => 'active',
            'approved_by_user_id' => $director->id,
        ]);
    }

    public function test_setting_draft_creation_and_director_approval_activates_new_version(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();

        $draftId = $this->actingAs($admin)
            ->postJson(route('settings.system-settings.store'), [
                'company_id' => $company->id,
                'setting_group' => 'after_sales',
                'setting_key' => 'after_sales.sla_hours',
                'label' => 'After-Sales SLA Hours',
                'description' => 'Updated SLA configuration for premium support.',
                'value_type' => 'object',
                'value' => [
                    'low' => 96,
                    'medium' => 48,
                    'high' => 18,
                    'critical' => 6,
                ],
                'effective_from' => now()->addDay()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version', 2)
            ->json('data.id');

        $draft = SystemSetting::findOrFail($draftId);

        $this->actingAs($admin)
            ->patchJson(route('settings.system-settings.approve', $draft), [
                'note' => 'Creator cannot self-approve.',
            ])
            ->assertForbidden();

        $this->actingAs($director)
            ->patchJson(route('settings.system-settings.approve', $draft), [
                'note' => 'Approved revised SLA.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.approved_by.email', 'aditya.mehra@builder360.test');

        $previousVersion = SystemSetting::where('scope_key', 'company:'.$company->id)
            ->where('setting_key', 'after_sales.sla_hours')
            ->where('version', 1)
            ->firstOrFail();

        $this->assertSame('active', $previousVersion->status);
        $this->assertSame(now()->toDateString(), $previousVersion->effective_to?->toDateString());

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'settings.system_setting.approved',
            'user_id' => $director->id,
        ]);

        $resolver = app(SystemSettingResolver::class);

        $this->assertSame(8, $resolver->value($company->id, 'after_sales.sla_hours')['critical']);
        $this->assertSame(6, $resolver->value(
            $company->id,
            'after_sales.sla_hours',
            effectiveOn: now()->addDay(),
        )['critical']);
    }

    public function test_system_admin_can_create_collaboration_task_settings_draft(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();

        $response = $this->actingAs($admin)
            ->postJson(route('settings.system-settings.store'), [
                'company_id' => $company->id,
                'setting_group' => 'collaboration',
                'setting_key' => 'collaboration.task_settings',
                'label' => 'Collaboration Task Settings',
                'description' => 'Updated Task Management workflow and notification controls.',
                'value_type' => 'object',
                'value' => [
                    'auto_progress' => true,
                    'require_completion_approval' => false,
                    'lock_completed' => true,
                    'transfer_requires_approval' => true,
                    'auto_archive_days' => 45,
                    'notifications' => [
                        'assignment' => true,
                        'comments_mentions' => true,
                        'due_soon' => true,
                        'overdue' => false,
                    ],
                    'status_map' => [
                        'open' => 'todo',
                        'in_progress' => 'inprogress',
                        'blocked' => 'blocked',
                        'completed' => 'done',
                        'cancelled' => 'cancelled',
                    ],
                    'templates' => [
                        [
                            'id' => 'handover-checklist',
                            'name' => 'Handover Checklist',
                            'cat' => 'Possession',
                            'desc' => 'Standard possession handover follow-up workflow.',
                            'icon' => 'checklist',
                            'color' => '#2570eb',
                            'uses' => 0,
                            'steps' => [
                                'Verify final payment',
                                'Complete snag review',
                                'Release possession letter',
                            ],
                        ],
                    ],
                ],
                'metadata' => ['source' => 'task_template_builder'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.setting_group', 'collaboration')
            ->assertJsonPath('data.setting_key', 'collaboration.task_settings')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.value.auto_archive_days', 45)
            ->assertJsonPath('data.value.templates.0.id', 'handover-checklist')
            ->assertJsonPath('data.value.templates.0.steps.2', 'Release possession letter')
            ->assertJsonPath('data.metadata.source', 'task_template_builder');

        $this->assertDatabaseHas('system_settings', [
            'company_id' => $company->id,
            'setting_key' => 'collaboration.task_settings',
            'status' => 'draft',
            'version' => 2,
        ]);
    }

    public function test_collaboration_task_template_drafts_validate_template_shape(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();

        $this->actingAs($admin)
            ->postJson(route('settings.system-settings.store'), [
                'company_id' => $company->id,
                'setting_group' => 'collaboration',
                'setting_key' => 'collaboration.task_settings',
                'label' => 'Collaboration Task Settings',
                'value_type' => 'object',
                'value' => [
                    'templates' => [
                        [
                            'id' => 'invalid id',
                            'name' => '',
                            'cat' => 'Operations',
                            'steps' => [],
                        ],
                        [
                            'id' => 'duplicate-template',
                            'name' => 'Valid Name',
                            'cat' => '',
                            'steps' => ['Review'],
                        ],
                        [
                            'id' => 'duplicate-template',
                            'name' => 'Duplicate Name',
                            'cat' => 'Operations',
                            'steps' => ['Review'],
                        ],
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'value.templates.0.id',
                'value.templates.0.name',
                'value.templates.0.steps',
                'value.templates.1.cat',
                'value.templates.2.id',
            ]);
    }

    public function test_system_admin_can_create_collaboration_mailbox_settings_draft(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();

        $this->actingAs($admin)
            ->postJson(route('settings.system-settings.store'), [
                'company_id' => $company->id,
                'setting_group' => 'collaboration',
                'setting_key' => 'collaboration.mailbox_settings',
                'label' => 'Collaboration Mailbox Settings',
                'description' => 'Updated governed mailbox metadata and notification controls.',
                'value_type' => 'object',
                'value' => [
                    'internal_messages_enabled' => true,
                    'external_sync_enabled' => false,
                    'allowed_providers' => ['internal_builder360', 'google_oauth_metadata', 'imap_smtp_metadata'],
                    'accounts' => [
                        [
                            'id' => 'acc-imap-metadata-test',
                            'provider' => 'imap_smtp_metadata',
                            'email' => 'sales@example.test',
                            'name' => 'Sales Mailbox Metadata',
                            'authType' => 'IMAP / SMTP metadata',
                            'syncStatus' => 'pending_approval',
                            'lastSync' => 'not connected',
                        ],
                    ],
                    'sync_scope' => [
                        'inbox' => true,
                        'sent' => true,
                        'archived' => true,
                        'trash' => false,
                        'spam' => false,
                        'historical' => false,
                        'frequency' => 'manual',
                    ],
                    'crm_linking' => [
                        'auto_match' => true,
                        'auto_create_contacts' => false,
                        'domain_link' => true,
                        'deal_link' => true,
                        'ignore_newsletters' => true,
                        'ignore_no_reply' => true,
                        'review_queue' => true,
                    ],
                    'notifications' => [
                        'new_email' => true,
                        'failed_sync' => true,
                        'failed_send' => true,
                        'in_app' => true,
                        'desktop' => false,
                    ],
                ],
                'metadata' => ['source' => 'mailbox_settings_screen'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.setting_group', 'collaboration')
            ->assertJsonPath('data.setting_key', 'collaboration.mailbox_settings')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.value.accounts.0.provider', 'imap_smtp_metadata')
            ->assertJsonPath('data.value.external_sync_enabled', false);

        $this->assertDatabaseHas('system_settings', [
            'company_id' => $company->id,
            'setting_key' => 'collaboration.mailbox_settings',
            'status' => 'draft',
            'version' => 2,
        ]);
    }

    public function test_system_admin_can_create_hr_custom_mis_report_definition_draft(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();

        $this->actingAs($admin)
            ->postJson(route('settings.system-settings.store'), [
                'company_id' => $company->id,
                'setting_group' => 'hr',
                'setting_key' => 'hr.custom_mis_reports',
                'label' => 'Custom HR MIS - Payroll Register',
                'description' => 'Governed HR custom MIS report definition.',
                'value_type' => 'object',
                'value' => [
                    'name' => 'Custom HR MIS - Payroll Register',
                    'report_type' => 'Payroll',
                    'filters' => [
                        'company_id' => $company->id,
                        'department' => 'HR',
                        'period' => 'FY 2025-26',
                        'status' => 'active',
                    ],
                    'columns' => ['Employee Code', 'Employee Name', 'Department', 'Monthly CTC'],
                    'formats' => ['csv', 'excel', 'pdf'],
                    'include_compensation' => true,
                    'approval_required' => true,
                    'generated_from' => 'hr_reports_screen',
                ],
                'metadata' => ['source' => 'hr_reports_custom_mis_builder'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.setting_group', 'hr')
            ->assertJsonPath('data.setting_key', 'hr.custom_mis_reports')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.value.report_type', 'Payroll')
            ->assertJsonPath('data.value.formats.2', 'pdf')
            ->assertJsonPath('data.metadata.source', 'hr_reports_custom_mis_builder');

        $this->assertDatabaseHas('system_settings', [
            'company_id' => $company->id,
            'setting_key' => 'hr.custom_mis_reports',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->postJson(route('settings.system-settings.store'), [
                'company_id' => $company->id,
                'setting_group' => 'hr',
                'setting_key' => 'hr.custom_mis_reports',
                'label' => 'Broken HR MIS',
                'value_type' => 'object',
                'value' => [
                    'name' => '',
                    'report_type' => 'Payroll',
                    'filters' => [],
                    'columns' => [],
                    'formats' => [''],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['value.name', 'value.columns', 'value.formats.0']);
    }

    public function test_system_admin_can_create_crm_lead_quality_score_rules_draft(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();

        $response = $this->actingAs($admin)
            ->postJson(route('settings.system-settings.store'), [
                'company_id' => $company->id,
                'setting_group' => 'crm',
                'setting_key' => 'crm.lead_quality_score.rules',
                'label' => 'CRM Lead Quality Score Rules',
                'description' => 'Configured lead qualification condition options and score bands.',
                'value_type' => 'object',
                'value' => [
                    'criteria' => [
                        'budget' => [
                            'label' => 'Budget Fit',
                            'max_points' => 40,
                            'options' => [
                                ['value' => 'budget_unverified', 'label' => 'Budget unverified', 'points' => 0],
                                ['value' => 'token_paid', 'label' => 'Token paid / budget committed', 'points' => 40],
                            ],
                        ],
                        'authority' => [
                            'label' => 'Decision Authority',
                            'max_points' => 20,
                            'options' => [
                                ['value' => 'influencer', 'label' => 'Influencer only', 'points' => 8],
                                ['value' => 'owner_decision', 'label' => 'Owner decision maker', 'points' => 20],
                            ],
                        ],
                        'need' => [
                            'label' => 'Requirement Clarity',
                            'max_points' => 20,
                            'options' => [
                                ['value' => 'generic', 'label' => 'Generic enquiry', 'points' => 5],
                                ['value' => 'unit_shortlisted', 'label' => 'Unit shortlisted', 'points' => 20],
                            ],
                        ],
                        'timeline' => [
                            'label' => 'Purchase Timeline',
                            'max_points' => 20,
                            'options' => [
                                ['value' => 'future', 'label' => 'Future timeline', 'points' => 5],
                                ['value' => 'ready_now', 'label' => 'Ready to book now', 'points' => 20],
                            ],
                        ],
                        'site_visit_readiness' => [
                            'label' => 'Site Visit Readiness',
                            'max_points' => 10,
                            'options' => [
                                ['value' => 'not_ready', 'label' => 'Not ready for site visit', 'points' => 0],
                                ['value' => 'visit_ready', 'label' => 'Ready for site visit', 'points' => 10],
                            ],
                        ],
                    ],
                    'bands' => [
                        ['label' => 'Priority Lead', 'min_score' => 80, 'status_hint' => 'qualified', 'tone' => 'green'],
                        ['label' => 'Review Lead', 'min_score' => 0, 'status_hint' => 'nurture', 'tone' => 'orange'],
                    ],
                ],
                'metadata' => ['source' => 'lead_quality_rule_builder'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.setting_group', 'crm')
            ->assertJsonPath('data.setting_key', 'crm.lead_quality_score.rules')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.value.criteria.budget.options.1.value', 'token_paid')
            ->assertJsonPath('data.value.criteria.site_visit_readiness.options.1.value', 'visit_ready')
            ->assertJsonPath('data.value.bands.0.label', 'Priority Lead');

        $this->assertDatabaseHas('system_settings', [
            'company_id' => $company->id,
            'setting_key' => 'crm.lead_quality_score.rules',
            'status' => 'draft',
        ]);
    }

    public function test_crm_lead_quality_score_rule_drafts_validate_condition_shape(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();

        $response = $this->actingAs($admin)
            ->postJson(route('settings.system-settings.store'), [
                'company_id' => $company->id,
                'setting_group' => 'crm',
                'setting_key' => 'crm.lead_quality_score.rules',
                'label' => 'CRM Lead Quality Score Rules',
                'value_type' => 'object',
                'value' => [
                    'criteria' => [
                        'budget' => [
                            'label' => 'Budget Fit',
                            'max_points' => 25,
                            'options' => [
                                ['value' => 'bad value', 'label' => 'Bad value', 'points' => 30],
                            ],
                        ],
                        'authority' => ['label' => 'Authority', 'max_points' => 25, 'options' => [['value' => 'owner', 'label' => 'Owner', 'points' => 25]]],
                        'need' => ['label' => 'Need', 'max_points' => 25, 'options' => [['value' => 'clear', 'label' => 'Clear', 'points' => 25]]],
                        'timeline' => ['label' => 'Timeline', 'max_points' => 25, 'options' => [['value' => 'now', 'label' => 'Now', 'points' => 25]]],
                        '1unsupported' => ['label' => 'Unsupported', 'max_points' => 10, 'options' => [['value' => 'x', 'label' => 'X', 'points' => 1]]],
                    ],
                    'bands' => [
                        ['label' => 'Invalid', 'min_score' => 110, 'status_hint' => 'bad', 'tone' => 'purple'],
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'value.criteria.1unsupported',
                'value.criteria.budget.options.0.value',
                'value.criteria.budget.options.0.points',
                'value.bands.0.min_score',
                'value.bands.0.status_hint',
                'value.bands.0.tone',
            ]);
    }

    public function test_crm_lead_quality_score_rule_drafts_require_unique_zero_floor_score_band(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();

        $response = $this->actingAs($admin)
            ->postJson(route('settings.system-settings.store'), [
                'company_id' => $company->id,
                'setting_group' => 'crm',
                'setting_key' => 'crm.lead_quality_score.rules',
                'label' => 'CRM Lead Quality Score Rules',
                'value_type' => 'object',
                'value' => [
                    'criteria' => [
                        'budget' => [
                            'label' => 'Budget Fit',
                            'max_points' => 25,
                            'options' => [
                                ['value' => 'low', 'label' => 'Low fit', 'points' => 5],
                                ['value' => 'high', 'label' => 'High fit', 'points' => 25],
                            ],
                        ],
                    ],
                    'bands' => [
                        ['label' => 'Priority', 'min_score' => 80, 'status_hint' => 'qualified', 'tone' => 'green'],
                        ['label' => 'Duplicate Priority', 'min_score' => 80, 'status_hint' => 'nurture', 'tone' => 'orange'],
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'value.bands',
                'value.bands.1.min_score',
            ]);

        $errors = $response->json('errors');
        $this->assertSame('Lead quality score rules must include a score band with minimum score 0.', $errors['value.bands'][0]);
        $this->assertSame('Score band minimum scores must be unique.', $errors['value.bands.1.min_score'][0]);
    }

    public function test_immediate_setting_approval_archives_current_active_version(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();

        $draftId = $this->actingAs($admin)
            ->postJson(route('settings.system-settings.store'), [
                'company_id' => $company->id,
                'setting_group' => 'after_sales',
                'setting_key' => 'after_sales.sla_hours',
                'label' => 'Immediate After-Sales SLA Hours',
                'description' => 'Immediate revised SLA configuration.',
                'value_type' => 'object',
                'value' => [
                    'low' => 96,
                    'medium' => 36,
                    'high' => 12,
                    'critical' => 4,
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version', 2)
            ->json('data.id');

        $draft = SystemSetting::findOrFail($draftId);

        $this->actingAs($director)
            ->patchJson(route('settings.system-settings.approve', $draft), [
                'note' => 'Approved immediate SLA.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $previousVersion = SystemSetting::where('scope_key', 'company:'.$company->id)
            ->where('setting_key', 'after_sales.sla_hours')
            ->where('version', 1)
            ->firstOrFail();

        $this->assertSame('archived', $previousVersion->status);
        $this->assertSame(now()->toDateString(), $previousVersion->effective_to?->toDateString());

        $this->assertSame(4, app(SystemSettingResolver::class)->value($company->id, 'after_sales.sla_hours')['critical']);
    }

    public function test_stale_older_draft_cannot_replace_a_newer_active_setting_version(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $service = app(SystemSettingService::class);

        $olderDraft = $service->createDraft([
            'company_id' => $company->id,
            'setting_group' => 'workflow',
            'setting_key' => 'workflow.concurrent_approval_guard',
            'label' => 'Concurrent Approval Guard',
            'value_type' => 'object',
            'value' => ['mode' => 'older'],
        ], $admin);

        $newerDraft = $service->createDraft([
            'company_id' => $company->id,
            'setting_group' => 'workflow',
            'setting_key' => 'workflow.concurrent_approval_guard',
            'label' => 'Concurrent Approval Guard',
            'value_type' => 'object',
            'value' => ['mode' => 'newer'],
        ], $admin);

        $this->actingAs($director)
            ->patchJson(route('settings.system-settings.approve', $newerDraft), [
                'note' => 'Approve the newest reviewed version.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->actingAs($director)
            ->patchJson(route('settings.system-settings.approve', $olderDraft), [
                'note' => 'This stale browser action must not roll settings back.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('setting')
            ->assertJsonPath(
                'errors.setting.0',
                'A newer setting version is already active. Refresh the register and review the latest version.',
            );

        $this->assertSame('draft', $olderDraft->fresh()->status);
        $this->assertSame('active', $newerDraft->fresh()->status);
        $this->assertSame('newer', app(SystemSettingResolver::class)->value(
            $company->id,
            'workflow.concurrent_approval_guard',
        )['mode']);
    }

    public function test_system_admin_cannot_create_setting_for_another_company(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();

        $this->actingAs($admin)
            ->postJson(route('settings.system-settings.store'), [
                'company_id' => $otherCompany->id,
                'setting_group' => 'workflow',
                'setting_key' => 'workflow.test',
                'label' => 'Invalid Workflow Setting',
                'value_type' => 'object',
                'value' => ['steps' => ['invalid']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company_id');
    }

    public function test_non_star_settings_users_without_company_assignment_fail_closed(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();

        $draftId = $this->actingAs($admin)
            ->postJson(route('settings.system-settings.store'), [
                'company_id' => $company->id,
                'setting_group' => 'workflow',
                'setting_key' => 'workflow.no_company_scope',
                'label' => 'No Company Scope Test',
                'value_type' => 'object',
                'value' => ['steps' => ['draft']],
            ])
            ->assertCreated()
            ->json('data.id');

        $draft = SystemSetting::findOrFail($draftId);

        $admin->forceFill(['company_id' => null])->save();

        $this->actingAs($admin)
            ->getJson(route('settings.system-settings.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($admin)
            ->postJson(route('settings.system-settings.store'), [
                'setting_group' => 'workflow',
                'setting_key' => 'workflow.invalid_global_from_no_company',
                'label' => 'Invalid Global From No Company',
                'value_type' => 'object',
                'value' => ['steps' => ['invalid']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company_id');

        $this->actingAs($admin)
            ->postJson(route('settings.system-settings.store'), [
                'company_id' => $company->id,
                'setting_group' => 'workflow',
                'setting_key' => 'workflow.invalid_company_from_no_company',
                'label' => 'Invalid Company From No Company',
                'value_type' => 'object',
                'value' => ['steps' => ['invalid']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company_id');

        $this->actingAs($admin)
            ->patchJson(route('settings.system-settings.approve', $draft), [
                'note' => 'Should fail without company scope.',
            ])
            ->assertForbidden();

        $this->actingAs($director)
            ->patchJson(route('settings.system-settings.approve', $draft), [
                'note' => 'Director can still approve scoped draft.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_system_setting_service_rejects_out_of_scope_direct_approval(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360P')->firstOrFail();

        $companyDraft = SystemSetting::create([
            'company_id' => $company->id,
            'created_by_user_id' => $admin->id,
            'scope_key' => 'company:'.$company->id,
            'setting_group' => 'workflow',
            'setting_key' => 'workflow.out_of_scope_direct_approval',
            'label' => 'Out-of-Scope Direct Approval',
            'value_type' => 'object',
            'value' => ['steps' => ['company_draft', 'approve']],
            'status' => 'draft',
            'version' => 1,
            'workflow_history' => [],
            'metadata' => ['source' => 'test'],
        ]);

        $this->actingAs($admin)
            ->patchJson(route('settings.system-settings.approve', $companyDraft), [
                'note' => 'Route policy should reject out-of-scope approval.',
            ])
            ->assertForbidden();

        try {
            app(SystemSettingService::class)->approve($companyDraft, [
                'note' => 'Direct service call must also reject out-of-scope approval.',
            ], $admin);

            $this->fail('Out-of-scope system setting approval was not rejected by the service layer.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('setting', $exception->errors());
        }

        $this->actingAs($director)
            ->patchJson(route('settings.system-settings.approve', $companyDraft), [
                'note' => 'Single-company director cannot approve another company setting.',
            ])
            ->assertForbidden();

        $this->assertSame('draft', $companyDraft->fresh()->status);
    }

    public function test_wildcard_director_system_setting_mutations_are_bound_to_active_company(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $activeCompany = Company::where('code', 'B360D')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();

        $this->actingAs($director)
            ->postJson(route('settings.system-settings.store'), [
                'company_id' => $otherCompany->id,
                'setting_group' => 'workflow',
                'setting_key' => 'workflow.cross_company_wildcard',
                'label' => 'Cross-Company Wildcard Setting',
                'value_type' => 'object',
                'value' => ['steps' => ['blocked']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company_id');

        $activeCompanyDraft = app(SystemSettingService::class)->createDraft([
            'setting_group' => 'workflow',
            'setting_key' => 'workflow.active_company_default',
            'label' => 'Active Company Default Setting',
            'value_type' => 'object',
            'value' => ['steps' => ['active_company']],
        ], $director);

        $this->assertSame($activeCompany->id, $activeCompanyDraft->company_id);
        $this->assertSame('company:'.$activeCompany->id, $activeCompanyDraft->scope_key);

        try {
            app(SystemSettingService::class)->createDraft([
                'company_id' => $otherCompany->id,
                'setting_group' => 'workflow',
                'setting_key' => 'workflow.cross_company_direct_service',
                'label' => 'Cross-Company Direct Service Setting',
                'value_type' => 'object',
                'value' => ['steps' => ['blocked']],
            ], $director);

            $this->fail('A wildcard director created a setting outside the active company through the service layer.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('company_id', $exception->errors());
        }

        $otherCompanyDraft = SystemSetting::create([
            'company_id' => $otherCompany->id,
            'created_by_user_id' => $admin->id,
            'scope_key' => 'company:'.$otherCompany->id,
            'setting_group' => 'workflow',
            'setting_key' => 'workflow.cross_company_direct_approval',
            'label' => 'Cross-Company Direct Approval',
            'value_type' => 'object',
            'value' => ['steps' => ['blocked']],
            'status' => 'draft',
            'version' => 1,
            'workflow_history' => [],
            'metadata' => ['source' => 'test'],
        ]);

        try {
            app(SystemSettingService::class)->approve($otherCompanyDraft, [
                'note' => 'Must remain inside the active operating company.',
            ], $director);

            $this->fail('A wildcard director approved a setting outside the active company through the service layer.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('setting', $exception->errors());
        }

        $this->assertSame('draft', $otherCompanyDraft->fresh()->status);
    }

    public function test_unrestricted_global_mode_preserves_legitimate_global_setting_creation(): void
    {
        $this->seed();
        config()->set('builder360.single_company.enabled', false);

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $setting = app(SystemSettingService::class)->createDraft([
            'setting_group' => 'workflow',
            'setting_key' => 'workflow.global_mode_setting',
            'label' => 'Global Mode Setting',
            'value_type' => 'object',
            'value' => ['steps' => ['global']],
        ], $director);

        $this->assertNull($setting->company_id);
        $this->assertSame('global', $setting->scope_key);
    }

    public function test_partner_and_auditor_cannot_mutate_settings(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $auditor = User::where('email', 'ishaan.trivedi@builder360.test')->firstOrFail();
        $setting = SystemSetting::where('setting_key', 'hr.attendance.rules')->firstOrFail();

        $this->actingAs($partner)
            ->getJson(route('settings.system-settings.index'))
            ->assertForbidden();

        $this->actingAs($auditor)
            ->postJson(route('settings.system-settings.store'), [])
            ->assertForbidden();

        $this->actingAs($auditor)
            ->patchJson(route('settings.system-settings.approve', $setting), [])
            ->assertForbidden();
    }

    public function test_validation_rejects_bad_setting_key_and_invalid_status_filter(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $this->actingAs($admin)
            ->postJson(route('settings.system-settings.store'), [
                'setting_group' => 'workflow',
                'setting_key' => 'Invalid Key With Spaces',
                'label' => 'Bad Setting',
                'value_type' => 'object',
                'value' => ['steps' => []],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('setting_key');

        $this->actingAs($admin)
            ->getJson(route('settings.system-settings.index', ['status' => 'published']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($admin)
            ->getJson(route('settings.system-settings.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($admin)
            ->getJson(route('settings.system-settings.index', ['company_id' => $admin->company_id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id'])
            ->assertJsonPath('errors.company_id.0', 'The selected filter is not available for this endpoint.');
    }

    public function test_system_setting_resolver_prefers_company_setting_then_global_fallback(): void
    {
        $this->seed();

        $companyWithSetting = Company::where('code', 'B360D')->firstOrFail();
        $companyWithoutSetting = Company::where('code', 'B360P')->firstOrFail();

        SystemSetting::create([
            'company_id' => null,
            'created_by_user_id' => null,
            'approved_by_user_id' => null,
            'scope_key' => 'global',
            'setting_group' => 'after_sales',
            'setting_key' => 'after_sales.sla_hours',
            'label' => 'Global After-Sales SLA',
            'value_type' => 'object',
            'value' => [
                'low' => 120,
                'medium' => 96,
                'high' => 48,
                'critical' => 12,
            ],
            'status' => 'active',
            'version' => 1,
            'effective_from' => now()->subDay()->toDateString(),
            'approved_at' => now(),
            'workflow_history' => [],
            'metadata' => ['source' => 'test'],
        ]);

        $resolver = app(SystemSettingResolver::class);

        $companyValue = $resolver->value($companyWithSetting->id, 'after_sales.sla_hours');
        $fallbackValue = $resolver->value($companyWithoutSetting->id, 'after_sales.sla_hours');

        $this->assertSame(8, $companyValue['critical']);
        $this->assertSame(12, $fallbackValue['critical']);
    }

    public function test_system_setting_resolver_ignores_future_and_expired_active_versions(): void
    {
        $this->seed();

        $company = Company::where('code', 'B360D')->firstOrFail();
        $baseline = SystemSetting::where('scope_key', 'company:'.$company->id)
            ->where('setting_key', 'after_sales.sla_hours')
            ->where('status', 'active')
            ->firstOrFail();

        SystemSetting::create([
            'company_id' => $company->id,
            'created_by_user_id' => null,
            'approved_by_user_id' => null,
            'scope_key' => 'company:'.$company->id,
            'setting_group' => 'after_sales',
            'setting_key' => 'after_sales.sla_hours',
            'label' => 'Future After-Sales SLA',
            'value_type' => 'object',
            'value' => [
                'low' => 48,
                'medium' => 24,
                'high' => 12,
                'critical' => 3,
            ],
            'status' => 'active',
            'version' => $baseline->version + 1,
            'effective_from' => now()->addDay()->toDateString(),
            'approved_at' => now(),
            'workflow_history' => [],
            'metadata' => ['source' => 'test'],
        ]);

        SystemSetting::create([
            'company_id' => $company->id,
            'created_by_user_id' => null,
            'approved_by_user_id' => null,
            'scope_key' => 'company:'.$company->id,
            'setting_group' => 'after_sales',
            'setting_key' => 'after_sales.sla_hours',
            'label' => 'Expired After-Sales SLA',
            'value_type' => 'object',
            'value' => [
                'low' => 200,
                'medium' => 100,
                'high' => 50,
                'critical' => 25,
            ],
            'status' => 'active',
            'version' => $baseline->version + 2,
            'effective_from' => now()->subDays(10)->toDateString(),
            'effective_to' => now()->subDay()->toDateString(),
            'approved_at' => now(),
            'workflow_history' => [],
            'metadata' => ['source' => 'test'],
        ]);

        $value = app(SystemSettingResolver::class)->value($company->id, 'after_sales.sla_hours');

        $this->assertSame(8, $value['critical']);
    }
}
