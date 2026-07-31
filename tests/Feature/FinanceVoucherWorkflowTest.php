<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FinancialVoucher;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceVoucherWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_user_can_open_native_blade_voucher_workspace(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();

        $this->actingAs($finance)
            ->get(route('finance.vouchers.index'))
            ->assertOk()
            ->assertSee('Financial Vouchers')
            ->assertSee('Workspace')
            ->assertSee('Submit voucher')
            ->assertSee('Voucher filters')
            ->assertSee('Voucher register')
            ->assertSee('name="voucher_type"', false)
            ->assertSee('name="lines[0][amount]"', false)
            ->assertSee('JV-10001')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);
    }

    public function test_native_blade_voucher_form_submits_and_redirects(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $this->actingAs($finance)
            ->post(route('finance.vouchers.store'), [
                'voucher_type' => 'payment',
                'voucher_date' => now()->toDateString(),
                'project_id' => $project->id,
                'reference_number' => 'BLADE-VCH-1001',
                'narration' => 'Native Blade payment voucher for project expense.',
                'currency' => 'INR',
                'lines' => [
                    [
                        'account_code' => 'CONTRACTOR-EXP',
                        'account_name' => 'Contractor Work Expense',
                        'line_type' => 'debit',
                        'amount' => 118000,
                        'project_id' => $project->id,
                        'cost_center' => 'Construction',
                        'tax_rate' => 18,
                        'tax_amount' => 18000,
                    ],
                    [
                        'account_code' => 'BANK-HDFC-001',
                        'account_name' => 'HDFC Bank Collection Account',
                        'line_type' => 'credit',
                        'amount' => 118000,
                        'project_id' => $project->id,
                        'cost_center' => 'Construction',
                    ],
                ],
            ])
            ->assertRedirect(route('finance.vouchers.index'))
            ->assertSessionHas('status');

        $voucher = FinancialVoucher::where('reference_number', 'BLADE-VCH-1001')->firstOrFail();

        $this->assertDatabaseHas('financial_vouchers', [
            'id' => $voucher->id,
            'project_id' => $project->id,
            'status' => 'submitted',
            'total_debit' => 118000,
            'total_credit' => 118000,
            'created_by_user_id' => $finance->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'finance.voucher.submitted',
            'auditable_type' => FinancialVoucher::class,
            'auditable_id' => $voucher->id,
            'user_id' => $finance->id,
        ]);
    }

    public function test_native_blade_voucher_approval_redirects_and_persists(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $this->actingAs($finance)
            ->post(route('finance.vouchers.store'), [
                'voucher_type' => 'journal',
                'voucher_date' => now()->toDateString(),
                'project_id' => $project->id,
                'reference_number' => 'BLADE-VCH-APPROVE',
                'narration' => 'Native Blade journal voucher pending approval.',
                'lines' => [
                    [
                        'account_code' => 'SUSPENSE',
                        'account_name' => 'Suspense Account',
                        'line_type' => 'debit',
                        'amount' => 5000,
                        'project_id' => $project->id,
                    ],
                    [
                        'account_code' => 'BANK-HDFC-001',
                        'account_name' => 'HDFC Bank Collection Account',
                        'line_type' => 'credit',
                        'amount' => 5000,
                        'project_id' => $project->id,
                    ],
                ],
            ])
            ->assertRedirect(route('finance.vouchers.index'));

        $voucher = FinancialVoucher::where('reference_number', 'BLADE-VCH-APPROVE')->firstOrFail();

        $this->actingAs($director)
            ->patch(route('finance.vouchers.approve', $voucher), [
                'note' => 'Approved from native Blade voucher register.',
            ])
            ->assertRedirect(route('finance.vouchers.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('financial_vouchers', [
            'id' => $voucher->id,
            'status' => 'approved',
            'approved_by_user_id' => $director->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'finance.voucher.approved',
            'auditable_type' => FinancialVoucher::class,
            'auditable_id' => $voucher->id,
            'user_id' => $director->id,
        ]);
    }

    public function test_finance_user_can_list_seeded_vouchers(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();

        $this->actingAs($finance)
            ->getJson(route('finance.vouchers.index', [
                'status' => 'approved',
                'voucher_type' => 'journal',
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.voucher_number', 'JV-10001')
            ->assertJsonPath('data.0.total_debit', 2500000)
            ->assertJsonPath('data.0.total_credit', 2500000)
            ->assertJsonCount(2, 'data.0.lines');
    }

    public function test_financial_voucher_index_rejects_unsupported_filters(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();

        $this->actingAs($finance)
            ->getJson(route('finance.vouchers.index', ['unexpected_filter' => 'ignored-before-hardening']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected_filter'])
            ->assertJsonPath('errors.unexpected_filter.0', 'The selected filter is not available for this endpoint.');
    }

    public function test_non_global_finance_user_without_company_assignment_fails_closed_for_vouchers(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $voucher = FinancialVoucher::where('voucher_number', 'JV-10001')->firstOrFail();
        $voucher->forceFill([
            'status' => 'submitted',
            'created_by_user_id' => $director->id,
            'approved_by_user_id' => null,
            'approved_at' => null,
        ])->save();

        $finance->forceFill(['company_id' => null])->save();

        $this->actingAs($finance)
            ->getJson(route('finance.vouchers.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($finance)
            ->getJson(route('finance.vouchers.index', ['project_id' => $project->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($finance)
            ->postJson(route('finance.vouchers.store'), [
                'voucher_type' => 'journal',
                'voucher_date' => now()->toDateString(),
                'project_id' => $project->id,
                'narration' => 'This voucher must be denied by company scope.',
                'lines' => [
                    [
                        'account_code' => 'SCOPE-DEBIT',
                        'account_name' => 'Scope Debit',
                        'line_type' => 'debit',
                        'amount' => 1000,
                        'project_id' => $project->id,
                    ],
                    [
                        'account_code' => 'SCOPE-CREDIT',
                        'account_name' => 'Scope Credit',
                        'line_type' => 'credit',
                        'amount' => 1000,
                        'project_id' => $project->id,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($finance)
            ->patchJson(route('finance.vouchers.approve', $voucher), [
                'note' => 'This approval must be denied by company scope.',
            ])
            ->assertForbidden();
    }

    public function test_financial_voucher_submission_and_director_approval_workflow(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();

        $voucherNumber = $this->actingAs($finance)
            ->postJson(route('finance.vouchers.store'), [
                'voucher_type' => 'payment',
                'voucher_date' => now()->toDateString(),
                'project_id' => $project->id,
                'reference_number' => 'VENDOR-PAY-1001',
                'narration' => 'Vendor payment entry for approved civil work invoice.',
                'lines' => [
                    [
                        'account_code' => 'CONTRACTOR-EXP',
                        'account_name' => 'Contractor Work Expense',
                        'line_type' => 'debit',
                        'amount' => 118000,
                        'project_id' => $project->id,
                        'cost_center' => 'Construction',
                        'tax_rate' => 18,
                        'tax_amount' => 18000,
                    ],
                    [
                        'account_code' => 'BANK-HDFC-001',
                        'account_name' => 'HDFC Bank Collection Account',
                        'line_type' => 'credit',
                        'amount' => 118000,
                        'project_id' => $project->id,
                        'cost_center' => 'Construction',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.total_debit', 118000)
            ->assertJsonPath('data.tax_summary.total_tax_amount', 18000)
            ->json('data.voucher_number');

        $voucher = FinancialVoucher::where('voucher_number', $voucherNumber)->firstOrFail();

        $this->actingAs($finance)
            ->patchJson(route('finance.vouchers.approve', $voucher), [
                'note' => 'Attempt creator self approval.',
            ])
            ->assertForbidden();

        $this->actingAs($director)
            ->patchJson(route('finance.vouchers.approve', $voucher), [
                'note' => 'Approved after voucher review.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by.email', 'aditya.mehra@builder360.test');

        $this->assertDatabaseHas('financial_vouchers', [
            'id' => $voucher->id,
            'status' => 'approved',
            'approved_by_user_id' => $director->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'finance.voucher.approved',
            'auditable_type' => FinancialVoucher::class,
            'auditable_id' => $voucher->id,
        ]);
    }

    public function test_global_user_can_submit_company_level_voucher_with_explicit_company_scope(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();

        $voucherNumber = $this->actingAs($director)
            ->postJson(route('finance.vouchers.store'), [
                'company_id' => $company->id,
                'voucher_type' => 'journal',
                'voucher_date' => now()->toDateString(),
                'reference_number' => 'GLOBAL-JV-1001',
                'narration' => 'Company level adjustment entered by global finance authority.',
                'lines' => [
                    [
                        'account_code' => 'CORP-EXP',
                        'account_name' => 'Corporate Expense',
                        'line_type' => 'debit',
                        'amount' => 25000,
                    ],
                    [
                        'account_code' => 'CORP-BANK',
                        'account_name' => 'Corporate Bank',
                        'line_type' => 'credit',
                        'amount' => 25000,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.company.id', $company->id)
            ->assertJsonPath('data.project', null)
            ->assertJsonPath('data.total_debit', 25000)
            ->json('data.voucher_number');

        $this->assertDatabaseHas('financial_vouchers', [
            'voucher_number' => $voucherNumber,
            'company_id' => $company->id,
            'project_id' => null,
            'created_by_user_id' => $director->id,
            'status' => 'submitted',
        ]);

        $this->actingAs($director)
            ->postJson(route('finance.vouchers.store'), [
                'voucher_type' => 'journal',
                'voucher_date' => now()->toDateString(),
                'narration' => 'Single-company voucher uses the active company context.',
                'lines' => [
                    ['account_code' => 'A', 'account_name' => 'A', 'line_type' => 'debit', 'amount' => 1000],
                    ['account_code' => 'B', 'account_name' => 'B', 'line_type' => 'credit', 'amount' => 1000],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.company.id', $company->id);
    }

    public function test_unbalanced_voucher_is_rejected_and_submitted_voucher_can_be_rejected(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($finance)
            ->postJson(route('finance.vouchers.store'), [
                'voucher_type' => 'journal',
                'voucher_date' => now()->toDateString(),
                'narration' => 'Invalid unbalanced voucher should fail.',
                'lines' => [
                    [
                        'account_code' => 'MISC-EXP',
                        'account_name' => 'Miscellaneous Expense',
                        'line_type' => 'debit',
                        'amount' => 1000,
                    ],
                    [
                        'account_code' => 'BANK-HDFC-001',
                        'account_name' => 'HDFC Bank Collection Account',
                        'line_type' => 'credit',
                        'amount' => 900,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);

        $voucherNumber = $this->actingAs($finance)
            ->postJson(route('finance.vouchers.store'), [
                'voucher_type' => 'journal',
                'voucher_date' => now()->toDateString(),
                'narration' => 'Reversal voucher pending director rejection.',
                'lines' => [
                    [
                        'account_code' => 'SUSPENSE',
                        'account_name' => 'Suspense Account',
                        'line_type' => 'debit',
                        'amount' => 5000,
                    ],
                    [
                        'account_code' => 'BANK-HDFC-001',
                        'account_name' => 'HDFC Bank Collection Account',
                        'line_type' => 'credit',
                        'amount' => 5000,
                    ],
                ],
            ])
            ->assertCreated()
            ->json('data.voucher_number');

        $voucher = FinancialVoucher::where('voucher_number', $voucherNumber)->firstOrFail();

        $this->actingAs($director)
            ->patchJson(route('finance.vouchers.reject', $voucher), [
                'reason' => 'Supporting document mismatch.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'finance.voucher.rejected',
            'auditable_type' => FinancialVoucher::class,
            'auditable_id' => $voucher->id,
        ]);
    }

    public function test_partner_cannot_access_finance_vouchers(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $voucher = FinancialVoucher::where('voucher_number', 'JV-10001')->firstOrFail();

        $this->actingAs($partner)
            ->getJson(route('finance.vouchers.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('finance.vouchers.store'), [
                'voucher_type' => 'journal',
                'voucher_date' => now()->toDateString(),
                'narration' => 'Partner should not access finance vouchers.',
                'lines' => [
                    ['account_code' => 'A', 'account_name' => 'A', 'line_type' => 'debit', 'amount' => 1],
                    ['account_code' => 'B', 'account_name' => 'B', 'line_type' => 'credit', 'amount' => 1],
                ],
            ])
            ->assertForbidden();

        $this->actingAs($partner)
            ->patchJson(route('finance.vouchers.approve', $voucher), [
                'note' => 'Invalid approval.',
            ])
            ->assertForbidden();
    }
}
