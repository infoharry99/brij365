<?php

namespace App\Services\Payroll;

use App\Models\PayrollBankTransferBatch;
use App\Models\PayrollBankTransferItem;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Security\CompanyScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollBankTransferService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function prepare(PayrollRun $payrollRun, array $data, User $actor, ?Request $request = null): PayrollBankTransferBatch
    {
        return DB::transaction(function () use ($payrollRun, $data, $actor, $request): PayrollBankTransferBatch {
            $run = PayrollRun::query()
                ->with(['items.employee'])
                ->whereKey($payrollRun->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($run->status !== 'approved') {
                throw ValidationException::withMessages(['payroll_run' => 'Bank transfer batches can be prepared only after payroll finance approval.']);
            }

            if (! app(CompanyScopeService::class)->allows($actor, $run->company_id)) {
                throw ValidationException::withMessages(['payroll_run' => 'The payroll run does not belong to your company.']);
            }

            if (PayrollBankTransferBatch::query()
                ->where('payroll_run_id', $run->id)
                ->where('bank_name', $data['bank_name'])
                ->whereDate('payment_date', $data['payment_date'])
                ->exists()) {
                throw ValidationException::withMessages(['payment_date' => 'A bank transfer batch already exists for this payroll run, bank and payment date.']);
            }

            $preparedRows = $this->preparedRows($run);
            $this->assertValidRows($preparedRows);

            $controlTotal = round(collect($preparedRows)->sum('amount'), 2);
            $runTotal = round((float) $run->net_payable, 2);

            if ($controlTotal !== $runTotal) {
                throw ValidationException::withMessages(['payroll_run' => 'Bank batch control total must equal the approved payroll net payable.']);
            }

            $csvPayload = $this->buildCsvPayload($run, $preparedRows, $data);
            $checksum = hash('sha256', $csvPayload);

            $batch = PayrollBankTransferBatch::create([
                'company_id' => $run->company_id,
                'payroll_run_id' => $run->id,
                'prepared_by_user_id' => $actor->id,
                'batch_number' => $this->nextBatchNumber(),
                'bank_name' => $data['bank_name'],
                'payment_date' => $data['payment_date'],
                'status' => 'prepared',
                'item_count' => count($preparedRows),
                'control_total' => $controlTotal,
                'checksum' => $checksum,
                'csv_payload' => $csvPayload,
                'validation_summary' => [
                    'run_number' => $run->run_number,
                    'employee_count' => count($preparedRows),
                    'control_total' => $controlTotal,
                    'approved_payroll_total' => $runTotal,
                    'checksum_algorithm' => 'sha256',
                    'debit_account_last4' => substr($data['debit_account_number'], -4),
                    'validated_at' => now()->toISOString(),
                ],
                'workflow_history' => [
                    $this->historyEvent('prepared', $actor, 'Bank transfer batch prepared.'),
                ],
                'prepared_at' => now(),
            ]);

            foreach ($preparedRows as $row) {
                PayrollBankTransferItem::create([
                    'payroll_bank_transfer_batch_id' => $batch->id,
                    'payroll_run_item_id' => $row['payroll_run_item_id'],
                    'employee_id' => $row['employee_id'],
                    'employee_code' => $row['employee_code'],
                    'beneficiary_name' => $row['beneficiary_name'],
                    'bank_account_number_encrypted' => $row['account_number'],
                    'bank_account_last4' => substr($row['account_number'], -4),
                    'ifsc_code' => $row['ifsc_code'],
                    'amount' => $row['amount'],
                    'status' => 'prepared',
                    'metadata' => ['payroll_run_number' => $run->run_number],
                ]);
            }

            $this->auditLogger->record(
                $actor,
                'payroll.bank_batch.prepared',
                'Prepared payroll bank transfer batch',
                $batch,
                [
                    'batch_number' => $batch->batch_number,
                    'run_number' => $run->run_number,
                    'item_count' => $batch->item_count,
                    'control_total' => $batch->control_total,
                    'checksum' => $batch->checksum,
                ],
                $request,
            );

            return $batch->load($this->relations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function release(PayrollBankTransferBatch $payrollBankTransferBatch, array $data, User $actor, ?Request $request = null): PayrollBankTransferBatch
    {
        return DB::transaction(function () use ($payrollBankTransferBatch, $data, $actor, $request): PayrollBankTransferBatch {
            $batch = PayrollBankTransferBatch::query()
                ->with(['items', 'payrollRun'])
                ->whereKey($payrollBankTransferBatch->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($batch->status !== 'prepared') {
                throw ValidationException::withMessages(['batch' => 'Only prepared bank batches can be released.']);
            }

            if (! app(CompanyScopeService::class)->allows($actor, $batch->company_id)) {
                throw ValidationException::withMessages(['batch' => 'The selected bank batch is outside your company scope.']);
            }

            if ($batch->prepared_by_user_id === $actor->id) {
                throw ValidationException::withMessages(['batch' => 'The batch preparer cannot release the same bank batch.']);
            }

            $currentChecksum = hash('sha256', $batch->csv_payload);
            if (! hash_equals($batch->checksum, $currentChecksum)) {
                throw ValidationException::withMessages(['checksum' => 'Bank batch checksum verification failed.']);
            }

            $itemTotal = round((float) $batch->items->sum(fn (PayrollBankTransferItem $item): float => (float) $item->amount), 2);
            if ($itemTotal !== round((float) $batch->control_total, 2)) {
                throw ValidationException::withMessages(['control_total' => 'Bank batch item total does not match control total.']);
            }

            $history = $batch->workflow_history ?? [];
            $history[] = $this->historyEvent('released', $actor, $data['release_note'] ?? 'Bank transfer batch released.');

            $batch->forceFill([
                'status' => 'released',
                'released_by_user_id' => $actor->id,
                'released_at' => now(),
                'workflow_history' => $history,
            ])->save();

            PayrollBankTransferItem::where('payroll_bank_transfer_batch_id', $batch->id)->update(['status' => 'released']);

            $this->auditLogger->record(
                $actor,
                'payroll.bank_batch.released',
                'Released payroll bank transfer batch',
                $batch,
                [
                    'batch_number' => $batch->batch_number,
                    'run_number' => $batch->payrollRun?->run_number,
                    'control_total' => $batch->control_total,
                    'checksum' => $batch->checksum,
                ],
                $request,
            );

            return $batch->load($this->relations());
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function preparedRows(PayrollRun $run): array
    {
        return $run->items
            ->map(function ($item): array {
                $employee = $item->employee;
                $profile = $employee?->sensitive_profile ?? [];

                return [
                    'payroll_run_item_id' => $item->id,
                    'employee_id' => $employee?->id,
                    'employee_code' => (string) $employee?->employee_code,
                    'beneficiary_name' => (string) ($profile['bank_beneficiary_name'] ?? $employee?->name),
                    'account_number' => (string) ($profile['bank_account_number'] ?? ''),
                    'ifsc_code' => strtoupper((string) ($profile['bank_ifsc'] ?? '')),
                    'amount' => round((float) $item->net_payable, 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function assertValidRows(array $rows): void
    {
        if ($rows === []) {
            throw ValidationException::withMessages(['payroll_run' => 'The approved payroll run has no payable employees.']);
        }

        $employeeIds = collect($rows)->pluck('employee_id')->filter()->values();
        if ($employeeIds->count() !== $employeeIds->unique()->count()) {
            throw ValidationException::withMessages(['employees' => 'Duplicate employee rows are not allowed in a bank transfer batch.']);
        }

        $accountKeys = collect($rows)
            ->map(fn (array $row): string => $row['account_number'].'|'.$row['ifsc_code'])
            ->filter(fn (string $key): bool => $key !== '|')
            ->values();

        if ($accountKeys->count() !== $accountKeys->unique()->count()) {
            throw ValidationException::withMessages(['bank_accounts' => 'Duplicate employee bank accounts are not allowed in one batch.']);
        }

        foreach ($rows as $row) {
            if (! $row['employee_id']) {
                throw ValidationException::withMessages(['employee' => 'Every payroll run item must be linked to an employee.']);
            }

            if ($row['amount'] <= 0) {
                throw ValidationException::withMessages(['net_payable' => "Employee {$row['employee_code']} has zero or negative net payable."]);
            }

            if (! preg_match('/^[0-9]{6,32}$/', $row['account_number'])) {
                throw ValidationException::withMessages(['bank_account_number' => "Employee {$row['employee_code']} has an invalid or missing bank account number."]);
            }

            if (! preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $row['ifsc_code'])) {
                throw ValidationException::withMessages(['bank_ifsc' => "Employee {$row['employee_code']} has an invalid or missing IFSC code."]);
            }

            if (trim($row['beneficiary_name']) === '') {
                throw ValidationException::withMessages(['beneficiary_name' => "Employee {$row['employee_code']} has no beneficiary name."]);
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $data
     */
    private function buildCsvPayload(PayrollRun $run, array $rows, array $data): string
    {
        $lines = [[
            'batch_run_number',
            'payment_date',
            'debit_account_last4',
            'employee_code',
            'beneficiary_name',
            'account_number',
            'ifsc_code',
            'amount',
            'narration',
        ]];

        foreach ($rows as $row) {
            $lines[] = [
                $run->run_number,
                $data['payment_date'],
                substr($data['debit_account_number'], -4),
                $row['employee_code'],
                $row['beneficiary_name'],
                $row['account_number'],
                $row['ifsc_code'],
                number_format((float) $row['amount'], 2, '.', ''),
                $data['narration'] ?? sprintf('Salary %04d-%02d', $run->period_year, $run->period_month),
            ];
        }

        return collect($lines)
            ->map(fn (array $line): string => collect($line)->map(fn ($value): string => $this->csvValue((string) $value))->implode(','))
            ->implode("\n");
    }

    private function csvValue(string $value): string
    {
        $escaped = str_replace('"', '""', $value);

        return "\"{$escaped}\"";
    }

    /**
     * @return array<string, mixed>
     */
    private function historyEvent(string $status, User $actor, string $note): array
    {
        return [
            'status' => $status,
            'actor_user_id' => $actor->id,
            'actor' => $actor->name,
            'note' => $note,
            'at' => now()->toISOString(),
        ];
    }

    private function nextBatchNumber(): string
    {
        return sprintf('BNK-%05d', PayrollBankTransferBatch::query()->withTrashed()->count() + 10001);
    }

    /**
     * @return array<int, string>
     */
    public function relations(): array
    {
        return ['payrollRun', 'preparedBy', 'releasedBy', 'items.employee'];
    }
}
