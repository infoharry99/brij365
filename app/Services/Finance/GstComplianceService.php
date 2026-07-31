<?php

namespace App\Services\Finance;

use App\Models\GstEntry;
use App\Models\GstReturnPeriod;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Security\CompanyScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GstComplianceService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createEntry(array $data, User $actor, ?Request $request = null): GstEntry
    {
        return DB::transaction(function () use ($data, $actor, $request): GstEntry {
            $documentDate = Carbon::parse($data['document_date']);
            $totalTax = round((float) ($data['cgst_amount'] ?? 0) + (float) ($data['sgst_amount'] ?? 0) + (float) ($data['igst_amount'] ?? 0) + (float) ($data['cess_amount'] ?? 0), 2);
            $companyId = app(CompanyScopeService::class)->companyIdFor($actor);

            if ($companyId === null || $companyId <= 0) {
                throw ValidationException::withMessages([
                    'document_date' => 'GST entries require a valid company scope.',
                ]);
            }

            $entry = GstEntry::create([
                'company_id' => $companyId,
                'project_id' => $data['project_id'] ?? null,
                'created_by_user_id' => $actor->id,
                'entry_number' => $this->nextEntryNumber(),
                'period_year' => $documentDate->year,
                'period_month' => $documentDate->month,
                'document_date' => $documentDate->toDateString(),
                'document_number' => $data['document_number'],
                'party_name' => $data['party_name'],
                'party_gstin' => $data['party_gstin'] ?? null,
                'place_of_supply_state' => $data['place_of_supply_state'],
                'transaction_type' => $data['transaction_type'],
                'hsn_sac' => $data['hsn_sac'] ?? null,
                'tax_rate' => round((float) $data['tax_rate'], 2),
                'taxable_amount' => round((float) $data['taxable_amount'], 2),
                'cgst_amount' => round((float) ($data['cgst_amount'] ?? 0), 2),
                'sgst_amount' => round((float) ($data['sgst_amount'] ?? 0), 2),
                'igst_amount' => round((float) ($data['igst_amount'] ?? 0), 2),
                'cess_amount' => round((float) ($data['cess_amount'] ?? 0), 2),
                'total_tax_amount' => $totalTax,
                'status' => 'submitted',
                'metadata' => $data['metadata'] ?? ['source' => 'gst_compliance_service'],
                'workflow_history' => [
                    $this->historyEvent('submitted', $actor, 'GST entry submitted.'),
                ],
            ]);

            $this->auditLogger->record(
                $actor,
                'finance.gst_entry.submitted',
                'Submitted GST entry',
                $entry,
                [
                    'entry_number' => $entry->entry_number,
                    'period' => sprintf('%04d-%02d', $entry->period_year, $entry->period_month),
                    'transaction_type' => $entry->transaction_type,
                    'total_tax_amount' => $entry->total_tax_amount,
                ],
                $request,
            );

            return $entry->load($this->entryRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function approveEntry(GstEntry $gstEntry, array $data, User $actor, ?Request $request = null): GstEntry
    {
        return DB::transaction(function () use ($gstEntry, $data, $actor, $request): GstEntry {
            $entry = GstEntry::query()->whereKey($gstEntry->id)->lockForUpdate()->firstOrFail();

            if (! app(CompanyScopeService::class)->allows($actor, $entry->company_id)) {
                throw ValidationException::withMessages(['gst_entry' => 'The selected GST entry is outside your company scope.']);
            }

            if ($entry->status !== 'submitted') {
                throw ValidationException::withMessages(['gst_entry' => 'Only submitted GST entries can be approved.']);
            }

            if ($entry->created_by_user_id === $actor->id) {
                throw ValidationException::withMessages(['gst_entry' => 'The GST entry creator cannot approve the same entry.']);
            }

            $this->assertPeriodNotLocked((int) $entry->company_id, (int) $entry->period_year, (int) $entry->period_month);

            $history = $entry->workflow_history ?? [];
            $history[] = $this->historyEvent('approved', $actor, $data['note'] ?? 'GST entry approved.');

            $entry->forceFill([
                'status' => 'approved',
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'finance.gst_entry.approved',
                'Approved GST entry',
                $entry,
                ['entry_number' => $entry->entry_number, 'total_tax_amount' => $entry->total_tax_amount],
                $request,
            );

            return $entry->load($this->entryRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function prepareReturnPeriod(array $data, User $actor, ?Request $request = null): GstReturnPeriod
    {
        return DB::transaction(function () use ($data, $actor, $request): GstReturnPeriod {
            $companyId = app(CompanyScopeService::class)->companyIdFor($actor);

            if ($companyId === null || $companyId <= 0) {
                throw ValidationException::withMessages(['period_month' => 'GST return periods require a valid company scope.']);
            }

            $periodStart = Carbon::create((int) $data['period_year'], (int) $data['period_month'], 1)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();

            if (GstReturnPeriod::query()
                ->where('company_id', $companyId)
                ->where('period_year', $periodStart->year)
                ->where('period_month', $periodStart->month)
                ->exists()) {
                throw ValidationException::withMessages(['period_month' => 'A GST return period already exists for this company and month.']);
            }

            $entries = GstEntry::query()
                ->where('company_id', $companyId)
                ->where('period_year', $periodStart->year)
                ->where('period_month', $periodStart->month)
                ->where('status', 'approved')
                ->lockForUpdate()
                ->get();

            if ($entries->isEmpty()) {
                throw ValidationException::withMessages(['period_month' => 'No approved GST entries were found for this return period.']);
            }

            $summary = $this->summarizeEntries($entries);

            $period = GstReturnPeriod::create([
                'company_id' => $companyId,
                'prepared_by_user_id' => $actor->id,
                'return_number' => $this->nextReturnNumber(),
                'period_year' => $periodStart->year,
                'period_month' => $periodStart->month,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'status' => 'prepared',
                'entry_count' => $entries->count(),
                'output_taxable_total' => $summary['output_taxable_total'],
                'output_tax_total' => $summary['output_tax_total'],
                'input_taxable_total' => $summary['input_taxable_total'],
                'input_tax_credit_total' => $summary['input_tax_credit_total'],
                'net_tax_payable' => $summary['net_tax_payable'],
                'summary' => $summary + ['note' => $data['note'] ?? null],
                'workflow_history' => [
                    $this->historyEvent('prepared', $actor, $data['note'] ?? 'GST return period prepared.'),
                ],
            ]);

            $this->auditLogger->record(
                $actor,
                'finance.gst_return.prepared',
                'Prepared GST return period',
                $period,
                [
                    'return_number' => $period->return_number,
                    'period' => sprintf('%04d-%02d', $period->period_year, $period->period_month),
                    'net_tax_payable' => $period->net_tax_payable,
                    'entry_count' => $period->entry_count,
                ],
                $request,
            );

            return $period->load($this->periodRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function approveReturnPeriod(GstReturnPeriod $gstReturnPeriod, array $data, User $actor, ?Request $request = null): GstReturnPeriod
    {
        return DB::transaction(function () use ($gstReturnPeriod, $data, $actor, $request): GstReturnPeriod {
            $period = GstReturnPeriod::query()->whereKey($gstReturnPeriod->id)->lockForUpdate()->firstOrFail();

            if (! app(CompanyScopeService::class)->allows($actor, $period->company_id)) {
                throw ValidationException::withMessages(['gst_return_period' => 'The selected GST return period is outside your company scope.']);
            }

            if ($period->status !== 'prepared') {
                throw ValidationException::withMessages(['gst_return_period' => 'Only prepared GST return periods can be approved.']);
            }

            if ($period->prepared_by_user_id === $actor->id) {
                throw ValidationException::withMessages(['gst_return_period' => 'The GST return preparer cannot approve the same period.']);
            }

            $history = $period->workflow_history ?? [];
            $history[] = $this->historyEvent('approved', $actor, $data['note'] ?? 'GST return period approved.');

            $period->forceFill([
                'status' => 'approved',
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'finance.gst_return.approved',
                'Approved GST return period',
                $period,
                ['return_number' => $period->return_number, 'net_tax_payable' => $period->net_tax_payable],
                $request,
            );

            return $period->load($this->periodRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function lockReturnPeriod(GstReturnPeriod $gstReturnPeriod, array $data, User $actor, ?Request $request = null): GstReturnPeriod
    {
        return DB::transaction(function () use ($gstReturnPeriod, $data, $actor, $request): GstReturnPeriod {
            $period = GstReturnPeriod::query()->whereKey($gstReturnPeriod->id)->lockForUpdate()->firstOrFail();

            if (! app(CompanyScopeService::class)->allows($actor, $period->company_id)) {
                throw ValidationException::withMessages(['gst_return_period' => 'The selected GST return period is outside your company scope.']);
            }

            if ($period->status !== 'approved') {
                throw ValidationException::withMessages(['gst_return_period' => 'Only approved GST return periods can be locked.']);
            }

            $history = $period->workflow_history ?? [];
            $history[] = $this->historyEvent('locked', $actor, $data['note'] ?? 'GST return period locked.');

            $period->forceFill([
                'status' => 'locked',
                'locked_by_user_id' => $actor->id,
                'locked_at' => now(),
                'workflow_history' => $history,
            ])->save();

            GstEntry::query()
                ->where('company_id', $period->company_id)
                ->where('period_year', $period->period_year)
                ->where('period_month', $period->period_month)
                ->where('status', 'approved')
                ->update(['status' => 'locked']);

            $this->auditLogger->record(
                $actor,
                'finance.gst_return.locked',
                'Locked GST return period',
                $period,
                ['return_number' => $period->return_number],
                $request,
            );

            return $period->load($this->periodRelations());
        });
    }

    private function assertPeriodNotLocked(int $companyId, int $periodYear, int $periodMonth): void
    {
        $locked = GstReturnPeriod::query()
            ->where('company_id', $companyId)
            ->where('period_year', $periodYear)
            ->where('period_month', $periodMonth)
            ->where('status', 'locked')
            ->exists();

        if ($locked) {
            throw ValidationException::withMessages(['period' => 'This GST return period is locked.']);
        }
    }

    /**
     * @param Collection<int, GstEntry> $entries
     * @return array<string, mixed>
     */
    private function summarizeEntries(Collection $entries): array
    {
        $output = $entries->whereIn('transaction_type', ['output', 'reverse_charge']);
        $input = $entries->where('transaction_type', 'input');
        $adjustment = $entries->where('transaction_type', 'adjustment');

        $outputTaxTotal = round((float) $output->sum('total_tax_amount') + (float) $adjustment->where('total_tax_amount', '>', 0)->sum('total_tax_amount'), 2);
        $inputTaxCreditTotal = round((float) $input->sum('total_tax_amount') + abs((float) $adjustment->where('total_tax_amount', '<', 0)->sum('total_tax_amount')), 2);

        return [
            'entry_count' => $entries->count(),
            'output_entry_count' => $output->count(),
            'input_entry_count' => $input->count(),
            'adjustment_entry_count' => $adjustment->count(),
            'output_taxable_total' => round((float) $output->sum('taxable_amount'), 2),
            'output_tax_total' => $outputTaxTotal,
            'input_taxable_total' => round((float) $input->sum('taxable_amount'), 2),
            'input_tax_credit_total' => $inputTaxCreditTotal,
            'net_tax_payable' => max(round($outputTaxTotal - $inputTaxCreditTotal, 2), 0),
            'by_transaction_type' => $entries->groupBy('transaction_type')->map(fn (Collection $rows): array => [
                'count' => $rows->count(),
                'taxable_amount' => round((float) $rows->sum('taxable_amount'), 2),
                'total_tax_amount' => round((float) $rows->sum('total_tax_amount'), 2),
            ])->all(),
            'prototype_notice' => 'GST computation is a configurable compliance register and requires client-appointed tax expert validation before production filing.',
        ];
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

    private function nextEntryNumber(): string
    {
        return sprintf('GST-%05d', GstEntry::query()->withTrashed()->count() + 10001);
    }

    private function nextReturnNumber(): string
    {
        return sprintf('GSTR-%05d', GstReturnPeriod::query()->withTrashed()->count() + 10001);
    }

    /**
     * @return array<int, string>
     */
    public function entryRelations(): array
    {
        return ['project', 'createdBy', 'approvedBy'];
    }

    /**
     * @return array<int, string>
     */
    public function periodRelations(): array
    {
        return ['company', 'preparedBy', 'approvedBy', 'lockedBy'];
    }
}
