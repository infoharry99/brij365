<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollBankTransferBatch;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollBankTransferBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_payroll_can_prepare_and_finance_can_release_bank_batch_from_blade_workspace(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $run = $this->approvedPayrollRun($payroll, $finance, now()->addMonthsNoOverflow(11));

        $this->actingAs($payroll)
            ->post(route('payroll.runs.bank-transfer-batches.store', $run), [
                'bank_name' => 'HDFC Bank',
                'payment_date' => now()->addDay()->toDateString(),
                'debit_account_number' => '998877665544',
                'narration' => 'Blade workspace salary disbursement',
            ])
            ->assertRedirect(route('payroll.bank-transfer-batches.index'));

        $batch = PayrollBankTransferBatch::query()
            ->where('payroll_run_id', $run->id)
            ->where('bank_name', 'HDFC Bank')
            ->firstOrFail();

        $this->assertSame('prepared', $batch->status);
        $this->assertSame(4, $batch->item_count);
        $this->assertSame('320000.00', (string) $batch->control_total);
        $this->assertNotEmpty($batch->checksum);

        $this->actingAs($finance)
            ->patch(route('payroll.bank-transfer-batches.release', $batch), [
                'release_note' => 'Released through the native Blade payroll workspace.',
            ])
            ->assertRedirect(route('payroll.bank-transfer-batches.index'));

        $batch->refresh();

        $this->assertSame('released', $batch->status);
        $this->assertSame($finance->id, $batch->released_by_user_id);
        $this->assertSame(
            'Released through the native Blade payroll workspace.',
            collect($batch->workflow_history)->last()['note']
        );
    }

    public function test_payroll_can_prepare_bank_batch_after_finance_approved_run_and_finance_can_release(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $run = $this->approvedPayrollRun($payroll, $finance, now()->addMonthsNoOverflow(4));

        $batchId = $this->actingAs($payroll)
            ->postJson(route('payroll.runs.bank-transfer-batches.store', $run), [
                'bank_name' => 'HDFC Bank',
                'payment_date' => now()->addDay()->toDateString(),
                'debit_account_number' => '998877665544',
                'narration' => 'Builder360 salary disbursement',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'prepared')
            ->assertJsonPath('data.item_count', 4)
            ->assertJsonPath('data.control_total', 320000)
            ->assertJsonPath('data.payroll_run.run_number', $run->run_number)
            ->json('data.id');

        $batch = PayrollBankTransferBatch::with('items')->findOrFail($batchId);

        $this->assertSame(hash('sha256', $batch->csv_payload), $batch->checksum);
        $this->assertStringContainsString('"employee_code","beneficiary_name","account_number","ifsc_code","amount"', $batch->csv_payload);
        $this->assertDatabaseCount('payroll_bank_transfer_items', 4);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'payroll.bank_batch.prepared',
            'auditable_id' => $batch->id,
            'user_id' => $payroll->id,
        ]);

        $this->actingAs($finance)
            ->patchJson(route('payroll.bank-transfer-batches.release', $batch), [
                'release_note' => 'Released after finance control total verification.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'released')
            ->assertJsonPath('data.released_by.email', 'suresh.iyer@builder360.test');

        $this->assertDatabaseHas('payroll_bank_transfer_batches', [
            'id' => $batch->id,
            'status' => 'released',
            'released_by_user_id' => $finance->id,
        ]);

        $this->assertDatabaseHas('payroll_bank_transfer_items', [
            'payroll_bank_transfer_batch_id' => $batch->id,
            'status' => 'released',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'payroll.bank_batch.released',
            'auditable_id' => $batch->id,
            'user_id' => $finance->id,
        ]);
    }

    public function test_batch_register_can_return_csv_payload_when_requested(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $run = $this->approvedPayrollRun($payroll, $finance, now()->addMonthsNoOverflow(5));

        $this->actingAs($payroll)
            ->postJson(route('payroll.runs.bank-transfer-batches.store', $run), [
                'bank_name' => 'ICICI Bank',
                'payment_date' => now()->addDays(2)->toDateString(),
                'debit_account_number' => '887766554433',
            ])
            ->assertCreated();

        $this->actingAs($payroll)
            ->getJson(route('payroll.bank-transfer-batches.index'))
            ->assertOk()
            ->assertJsonMissingPath('data.0.csv_payload');

        $this->actingAs($payroll)
            ->getJson(route('payroll.bank-transfer-batches.index', ['include_payload' => true]))
            ->assertForbidden();

        $this->actingAs($finance)
            ->getJson(route('payroll.bank-transfer-batches.index', ['include_payload' => true]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.bank_name', 'ICICI Bank')
            ->assertJsonFragment(['control_total' => 320000])
            ->assertJsonStructure(['data' => [['csv_payload', 'checksum', 'items']]]);
    }

    public function test_bank_batch_index_validates_filters_and_payroll_run_scope(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $run = $this->approvedPayrollRun($payroll, $finance, now()->addMonthsNoOverflow(8));
        $externalRun = $this->createExternalPayrollRun();
        $paymentDate = now()->addDays(3)->toDateString();

        $batchId = $this->actingAs($payroll)
            ->postJson(route('payroll.runs.bank-transfer-batches.store', $run), [
                'bank_name' => 'Axis Bank',
                'payment_date' => $paymentDate,
                'debit_account_number' => '776655443322',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($finance)
            ->getJson(route('payroll.bank-transfer-batches.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($finance)
            ->getJson(route('payroll.bank-transfer-batches.index', ['status' => 'cancelled']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($finance)
            ->getJson(route('payroll.bank-transfer-batches.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($finance)
            ->getJson(route('payroll.bank-transfer-batches.index', ['employee_id' => 1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id'])
            ->assertJsonPath('errors.employee_id.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($finance)
            ->getJson(route('payroll.bank-transfer-batches.index', [
                'from' => now()->addDay()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');

        $this->actingAs($finance)
            ->getJson(route('payroll.bank-transfer-batches.index', ['per_page' => 100]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');

        $this->actingAs($finance)
            ->getJson(route('payroll.bank-transfer-batches.index', ['payroll_run_id' => $externalRun->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payroll_run_id');

        $this->actingAs($finance)
            ->getJson(route('payroll.bank-transfer-batches.index', [
                'status' => 'prepared',
                'payroll_run_id' => $run->id,
                'bank_name' => 'Axis Bank',
                'from' => now()->toDateString(),
                'to' => now()->addWeek()->toDateString(),
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $batchId)
            ->assertJsonPath('data.0.bank_name', 'Axis Bank');
    }

    public function test_non_global_payroll_users_without_company_assignment_fail_closed_for_bank_batches(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $run = $this->approvedPayrollRun($payroll, $finance, now()->addMonthsNoOverflow(9));

        $batchId = $this->actingAs($payroll)
            ->postJson(route('payroll.runs.bank-transfer-batches.store', $run), [
                'bank_name' => 'Kotak Bank',
                'payment_date' => now()->addDays(3)->toDateString(),
                'debit_account_number' => '665544332211',
            ])
            ->assertCreated()
            ->json('data.id');

        $batch = PayrollBankTransferBatch::findOrFail($batchId);

        $payroll->forceFill(['company_id' => null])->save();
        $finance->forceFill(['company_id' => null])->save();

        $this->actingAs($payroll)
            ->getJson(route('payroll.bank-transfer-batches.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($finance)
            ->getJson(route('payroll.bank-transfer-batches.index', ['payroll_run_id' => $run->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payroll_run_id');

        $this->actingAs($payroll)
            ->postJson(route('payroll.runs.bank-transfer-batches.store', $run), [
                'bank_name' => 'HDFC Bank',
                'payment_date' => now()->addDays(4)->toDateString(),
                'debit_account_number' => '998877665544',
            ])
            ->assertForbidden();

        $this->actingAs($finance)
            ->patchJson(route('payroll.bank-transfer-batches.release', $batch), [
                'release_note' => 'Should fail closed.',
            ])
            ->assertForbidden();
    }

    public function test_bank_batch_cannot_be_prepared_before_approval_or_released_by_preparer(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $period = now()->addMonthsNoOverflow(6);

        $runNumber = $this->actingAs($payroll)
            ->postJson(route('payroll.runs.generate'), [
                'period_year' => $period->year,
                'period_month' => $period->month,
                'working_days' => 26,
            ])
            ->assertCreated()
            ->json('data.run_number');

        $run = PayrollRun::where('run_number', $runNumber)->firstOrFail();

        $this->actingAs($payroll)
            ->postJson(route('payroll.runs.bank-transfer-batches.store', $run), [
                'bank_name' => 'HDFC Bank',
                'payment_date' => now()->addDay()->toDateString(),
                'debit_account_number' => '998877665544',
            ])
            ->assertForbidden();

        $this->actingAs($finance)->patchJson(route('payroll.runs.approve', $run))->assertOk();
        $run->refresh();

        $batchId = $this->actingAs($payroll)
            ->postJson(route('payroll.runs.bank-transfer-batches.store', $run), [
                'bank_name' => 'HDFC Bank',
                'payment_date' => now()->addDay()->toDateString(),
                'debit_account_number' => '998877665544',
            ])
            ->assertCreated()
            ->json('data.id');

        $batch = PayrollBankTransferBatch::findOrFail($batchId);

        $this->actingAs($payroll)
            ->patchJson(route('payroll.bank-transfer-batches.release', $batch))
            ->assertForbidden();
    }

    public function test_bank_batch_rejects_invalid_employee_bank_configuration(): void
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();
        $profile = $employee->sensitive_profile;
        $profile['bank_ifsc'] = 'BADIFSC';
        $employee->forceFill(['sensitive_profile' => $profile])->save();

        $run = $this->approvedPayrollRun($payroll, $finance, now()->addMonthsNoOverflow(7));

        $this->actingAs($payroll)
            ->postJson(route('payroll.runs.bank-transfer-batches.store', $run), [
                'bank_name' => 'HDFC Bank',
                'payment_date' => now()->addDay()->toDateString(),
                'debit_account_number' => '998877665544',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bank_ifsc']);
    }

    public function test_partner_cannot_access_bank_transfer_batches(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $run = PayrollRun::query()->first();

        $this->actingAs($partner)
            ->getJson(route('payroll.bank-transfer-batches.index'))
            ->assertForbidden();

        if ($run) {
            $this->actingAs($partner)
                ->postJson(route('payroll.runs.bank-transfer-batches.store', $run), [
                    'bank_name' => 'HDFC Bank',
                    'payment_date' => now()->addDay()->toDateString(),
                    'debit_account_number' => '998877665544',
                ])
                ->assertForbidden();
        }
    }

    private function approvedPayrollRun(User $payroll, User $finance, \DateTimeInterface $period): PayrollRun
    {
        $runNumber = $this->actingAs($payroll)
            ->postJson(route('payroll.runs.generate'), [
                'period_year' => (int) $period->format('Y'),
                'period_month' => (int) $period->format('m'),
                'working_days' => 26,
            ])
            ->assertCreated()
            ->json('data.run_number');

        $run = PayrollRun::where('run_number', $runNumber)->firstOrFail();

        $this->actingAs($finance)
            ->patchJson(route('payroll.runs.approve', $run))
            ->assertOk();

        return $run->refresh();
    }

    private function createExternalPayrollRun(): PayrollRun
    {
        $company = Company::create([
            'code' => 'EXTPAY',
            'name' => 'External Payroll Co',
            'legal_name' => 'External Payroll Co Private Limited',
            'state' => 'MH',
            'status' => 'active',
        ]);

        return PayrollRun::create([
            'company_id' => $company->id,
            'generated_by_user_id' => null,
            'approved_by_user_id' => null,
            'run_number' => 'PAY-EXT-10001',
            'period_year' => now()->year,
            'period_month' => now()->month,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'working_days' => 26,
            'status' => 'approved',
            'gross_earnings' => 100000,
            'total_deductions' => 10000,
            'net_payable' => 90000,
            'metadata' => ['source' => 'scope-test'],
            'approved_at' => now(),
        ]);
    }
}
