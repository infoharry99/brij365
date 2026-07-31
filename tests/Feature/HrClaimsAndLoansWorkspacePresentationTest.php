<?php

namespace Tests\Feature;

use App\Application\Hr\Actions\ListEmployeeOperationsWorkspace;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HrClaimsAndLoansWorkspacePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_claims_and_loans_render_people_workspace_with_supported_status_contracts(): void
    {
        $this->seed();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $this->actingAs($hr)->get(route('hr.expense-claims.index'))
            ->assertOk()
            ->assertSee('People workspace')
            ->assertSee('Expense Claims')
            ->assertSee('Expense claims')
            ->assertSee('people-ops-kpis', false);

        $this->actingAs($hr)->get(route('hr.loans.index'))
            ->assertOk()
            ->assertSee('Loans &amp; Advances', false)
            ->assertSee('Employee loans')
            ->assertSee('Closed')
            ->assertDontSee('value="recovered"', false);

        $this->actingAs($hr)->getJson(route('hr.loans.index', ['status' => 'recovered']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_focused_claim_and_loan_pages_do_not_query_inactive_operation_registers(): void
    {
        $this->seed();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        app(ListEmployeeOperationsWorkspace::class)->execute($hr, 'claims');
        $claimSql = implode("\n", $queries);
        $this->assertStringNotContainsString('employee_loans', $claimSql);
        $this->assertStringNotContainsString('employee_assets', $claimSql);
        $this->assertStringNotContainsString('hr_helpdesk_tickets', $claimSql);

        $queries = [];
        app(ListEmployeeOperationsWorkspace::class)->execute($hr, 'loans');
        $loanSql = implode("\n", $queries);
        $this->assertStringNotContainsString('expense_claims', $loanSql);
        $this->assertStringNotContainsString('employee_assets', $loanSql);
        $this->assertStringNotContainsString('hr_helpdesk_tickets', $loanSql);
    }

    public function test_claim_kpis_use_complete_authorized_query_and_row_actions_use_policy_results(): void
    {
        $this->seed();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        ExpenseClaim::query()->delete();
        foreach (['submitted', 'approved', 'paid'] as $index => $status) {
            ExpenseClaim::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'requested_by_user_id' => $index === 0 ? $hr->id : $employeeUser->id,
                'claim_number' => 'CLAIM-PRESENT-'.$index,
                'claim_type' => 'travel',
                'status' => $status,
                'claim_date' => now()->toDateString(),
                'amount' => 1000 + $index,
                'approved_amount' => $status === 'submitted' ? 0 : 900 + $index,
                'currency' => 'INR',
                'description' => 'Presentation coverage expense claim number '.$index.'.',
                'workflow_history' => [],
            ]);
        }

        $selfRequested = ExpenseClaim::where('claim_number', 'CLAIM-PRESENT-0')->firstOrFail();
        $response = $this->actingAs($hr)->get(route('hr.expense-claims.index', ['per_page' => 1]));

        $response->assertOk()
            ->assertSeeTextInOrder(['Total claims', '3'])
            ->assertDontSee('claim-approve-'.$selfRequested->id, false);

        $otherSubmitted = ExpenseClaim::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'requested_by_user_id' => $employeeUser->id,
            'claim_number' => 'CLAIM-POLICY-ACTION',
            'claim_type' => 'fuel',
            'status' => 'submitted',
            'claim_date' => now()->toDateString(),
            'amount' => 1250,
            'currency' => 'INR',
            'description' => 'Claim policy action rendering verification.',
            'workflow_history' => [],
        ]);

        $response = $this->actingAs($hr)->get(route('hr.expense-claims.index', ['status' => 'submitted', 'per_page' => 50]));
        $response
            ->assertOk()
            ->assertSee('claim-approve-'.$otherSubmitted->id, false)
            ->assertDontSee('claim-approve-'.$selfRequested->id, false);

        $this->assertSame(2, substr_count($response->getContent(), route('hr.expense-claims.approve', $otherSubmitted)));
        $this->assertStringContainsString('claim-approve-'.$otherSubmitted->id.'-desktop', $response->getContent());
        $this->assertStringContainsString('claim-approve-'.$otherSubmitted->id.'-mobile', $response->getContent());
    }
}
