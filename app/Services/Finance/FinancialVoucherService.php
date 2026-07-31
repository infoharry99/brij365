<?php

namespace App\Services\Finance;

use App\Models\FinancialVoucher;
use App\Models\Project;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Security\CompanyScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancialVoucherService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CompanyScopeService $companyScope,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function submit(array $data, User $actor, ?Request $request = null): FinancialVoucher
    {
        return DB::transaction(function () use ($data, $actor, $request): FinancialVoucher {
            $totals = $this->calculateTotals($data['lines']);
            $companyId = $this->resolveVoucherCompanyId($data, $actor);

            if (round($totals['debit'], 2) !== round($totals['credit'], 2)) {
                throw ValidationException::withMessages([
                    'lines' => 'Voucher debit and credit totals must be equal.',
                ]);
            }

            $voucher = FinancialVoucher::create([
                'company_id' => $companyId,
                'project_id' => $data['project_id'] ?? null,
                'created_by_user_id' => $actor->id,
                'voucher_number' => $this->nextVoucherNumber(),
                'voucher_type' => $data['voucher_type'],
                'status' => 'submitted',
                'voucher_date' => $data['voucher_date'],
                'reference_number' => $data['reference_number'] ?? null,
                'narration' => $data['narration'],
                'currency' => strtoupper($data['currency'] ?? 'INR'),
                'total_debit' => $totals['debit'],
                'total_credit' => $totals['credit'],
                'tax_summary' => $totals['tax_summary'],
                'workflow_history' => [
                    $this->workflowEvent('submitted', $actor, 'Financial voucher submitted'),
                ],
                'metadata' => $data['metadata'] ?? [],
            ]);

            foreach (array_values($data['lines']) as $index => $line) {
                $voucher->lines()->create([
                    'project_id' => $line['project_id'] ?? $voucher->project_id,
                    'line_number' => $index + 1,
                    'account_code' => $line['account_code'],
                    'account_name' => $line['account_name'],
                    'line_type' => $line['line_type'],
                    'amount' => $line['amount'],
                    'party_type' => $line['party_type'] ?? null,
                    'party_id' => $line['party_id'] ?? null,
                    'cost_center' => $line['cost_center'] ?? null,
                    'tax_rate' => $line['tax_rate'] ?? 0,
                    'tax_amount' => $line['tax_amount'] ?? 0,
                    'description' => $line['description'] ?? null,
                    'metadata' => $line['metadata'] ?? [],
                ]);
            }

            $this->auditLogger->record(
                $actor,
                'finance.voucher.submitted',
                'Submitted financial voucher',
                $voucher,
                [
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_type' => $voucher->voucher_type,
                    'total_debit' => $voucher->total_debit,
                    'total_credit' => $voucher->total_credit,
                ],
                $request,
            );

            return $voucher->load($this->relations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function approve(FinancialVoucher $financialVoucher, array $data, User $actor, ?Request $request = null): FinancialVoucher
    {
        return DB::transaction(function () use ($financialVoucher, $data, $actor, $request): FinancialVoucher {
            $voucher = FinancialVoucher::query()
                ->whereKey($financialVoucher->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->companyScope->allows($actor, $voucher->company_id)) {
                throw ValidationException::withMessages(['voucher' => 'The selected voucher is outside your company scope.']);
            }

            if ($voucher->status !== 'submitted') {
                throw ValidationException::withMessages(['voucher' => 'Only submitted vouchers can be approved.']);
            }

            if ($voucher->created_by_user_id === $actor->id) {
                throw ValidationException::withMessages(['voucher' => 'The creator cannot approve the same voucher.']);
            }

            $history = $voucher->workflow_history ?? [];
            $history[] = $this->workflowEvent('approved', $actor, $data['note'] ?? 'Voucher approved');

            $voucher->forceFill([
                'status' => 'approved',
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'finance.voucher.approved',
                'Approved financial voucher',
                $voucher,
                ['voucher_number' => $voucher->voucher_number, 'voucher_type' => $voucher->voucher_type],
                $request,
            );

            return $voucher->load($this->relations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function reject(FinancialVoucher $financialVoucher, array $data, User $actor, ?Request $request = null): FinancialVoucher
    {
        return DB::transaction(function () use ($financialVoucher, $data, $actor, $request): FinancialVoucher {
            $voucher = FinancialVoucher::query()
                ->whereKey($financialVoucher->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->companyScope->allows($actor, $voucher->company_id)) {
                throw ValidationException::withMessages(['voucher' => 'The selected voucher is outside your company scope.']);
            }

            if ($voucher->status !== 'submitted') {
                throw ValidationException::withMessages(['voucher' => 'Only submitted vouchers can be rejected.']);
            }

            if ($voucher->created_by_user_id === $actor->id) {
                throw ValidationException::withMessages(['voucher' => 'The creator cannot reject the same voucher.']);
            }

            $history = $voucher->workflow_history ?? [];
            $history[] = $this->workflowEvent('rejected', $actor, $data['reason']);

            $voucher->forceFill([
                'status' => 'rejected',
                'approved_by_user_id' => $actor->id,
                'rejected_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'finance.voucher.rejected',
                'Rejected financial voucher',
                $voucher,
                ['voucher_number' => $voucher->voucher_number, 'reason' => $data['reason']],
                $request,
            );

            return $voucher->load($this->relations());
        });
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @return array{debit: float, credit: float, tax_summary: array<string, mixed>}
     */
    private function calculateTotals(array $lines): array
    {
        $debit = 0.0;
        $credit = 0.0;
        $taxAmount = 0.0;

        foreach ($lines as $line) {
            $amount = round((float) $line['amount'], 2);

            if ($line['line_type'] === 'debit') {
                $debit += $amount;
            } else {
                $credit += $amount;
            }

            $taxAmount += round((float) ($line['tax_amount'] ?? 0), 2);
        }

        return [
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'tax_summary' => [
                'total_tax_amount' => round($taxAmount, 2),
                'line_count' => count($lines),
            ],
        ];
    }

    /**
     * @return array<string, string|int>
     */
    private function workflowEvent(string $status, User $actor, string $note): array
    {
        return [
            'status' => $status,
            'actor_user_id' => $actor->id,
            'actor' => $actor->name,
            'note' => $note,
            'at' => now()->toISOString(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveVoucherCompanyId(array $data, User $actor): int
    {
        $explicitCompanyId = isset($data['company_id']) ? (int) $data['company_id'] : null;
        $projectIds = collect($data['lines'] ?? [])
            ->pluck('project_id')
            ->push($data['project_id'] ?? null)
            ->filter()
            ->unique()
            ->values();

        if ($projectIds->isNotEmpty()) {
            $projectCompanies = Project::query()
                ->whereIn('id', $projectIds->all())
                ->pluck('company_id', 'id');

            $invalidProjectExists = $projectCompanies
                ->contains(fn ($companyId): bool => ! $this->companyScope->allows($actor, $companyId));

            if ($invalidProjectExists) {
                throw ValidationException::withMessages(['project_id' => 'Voucher projects must belong to your company.']);
            }

            if ($explicitCompanyId !== null) {
                if (! $this->companyScope->allows($actor, $explicitCompanyId)) {
                    throw ValidationException::withMessages(['company_id' => 'The selected company is outside your company scope.']);
                }

                $mismatchedProjectExists = $projectCompanies
                    ->contains(fn ($projectCompanyId): bool => (int) $projectCompanyId !== $explicitCompanyId);

                if ($mismatchedProjectExists) {
                    throw ValidationException::withMessages(['company_id' => 'The selected company must match all voucher projects.']);
                }

                return $explicitCompanyId;
            }

            $projectCompanyIds = $projectCompanies->unique()->values();

            if ($projectCompanyIds->count() === 1) {
                return (int) $projectCompanyIds->first();
            }

            throw ValidationException::withMessages(['company_id' => 'A company is required when voucher lines reference projects from multiple companies.']);
        }

        if ($explicitCompanyId !== null) {
            if (! $this->companyScope->allows($actor, $explicitCompanyId)) {
                throw ValidationException::withMessages(['company_id' => 'The selected company is outside your company scope.']);
            }

            return $explicitCompanyId;
        }

        $companyId = $this->companyScope->companyIdFor($actor);

        if ($companyId === null || $companyId === 0) {
            throw ValidationException::withMessages(['company_id' => 'A company is required to submit a financial voucher.']);
        }

        return $companyId;
    }

    private function nextVoucherNumber(): string
    {
        return sprintf('JV-%05d', FinancialVoucher::query()->withTrashed()->count() + 10001);
    }

    /**
     * @return array<int, string>
     */
    public function relations(): array
    {
        return ['company', 'project', 'createdBy', 'approvedBy', 'lines.project'];
    }
}
