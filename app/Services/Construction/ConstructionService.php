<?php

namespace App\Services\Construction;

use App\Models\BoqItem;
use App\Models\ConstructionMilestone;
use App\Models\ContractorBill;
use App\Models\ContractorMeasurement;
use App\Models\DailyProgressReport;
use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Audit\AuditLogger;
use App\Services\Security\CompanyScopeService;
use App\Services\Settings\SystemSettingResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConstructionService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CompanyScopeService $companyScope,
        private readonly SystemSettingResolver $settings,
    )
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createMilestone(array $data, User $actor, ?Request $request = null): ConstructionMilestone
    {
        return DB::transaction(function () use ($data, $actor, $request): ConstructionMilestone {
            $project = $this->activeProjectForActor((int) $data['project_id'], $actor);

            $milestone = ConstructionMilestone::create([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'created_by_user_id' => $actor->id,
                'milestone_code' => $data['milestone_code'],
                'name' => $data['name'],
                'phase' => $data['phase'],
                'planned_start_on' => $data['planned_start_on'],
                'planned_end_on' => $data['planned_end_on'],
                'weight_percent' => $data['weight_percent'],
                'progress_percent' => 0,
                'status' => 'planned',
                'dependencies' => $data['dependencies'] ?? [],
                'metadata' => $data['metadata'] ?? ['source' => 'construction_service'],
            ]);

            $this->auditLogger->record(
                $actor,
                'construction.milestone.created',
                'Created construction milestone',
                $milestone,
                [
                    'milestone_code' => $milestone->milestone_code,
                    'project_id' => $milestone->project_id,
                    'weight_percent' => $milestone->weight_percent,
                ],
                $request,
            );

            return $milestone->load(['project', 'createdBy']);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createBoqItem(array $data, User $actor, ?Request $request = null): BoqItem
    {
        return DB::transaction(function () use ($data, $actor, $request): BoqItem {
            $project = $this->activeProjectForActor((int) $data['project_id'], $actor);
            $this->assertOptionalMilestoneScope($data['construction_milestone_id'] ?? null, $project);
            $this->assertOptionalVendorScope($data['vendor_id'] ?? null, $project);

            $budgetAmount = round((float) $data['planned_quantity'] * (float) $data['rate'], 2);

            $boqItem = BoqItem::create([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'construction_milestone_id' => $data['construction_milestone_id'] ?? null,
                'vendor_id' => $data['vendor_id'] ?? null,
                'created_by_user_id' => $actor->id,
                'boq_code' => $data['boq_code'],
                'trade' => $data['trade'],
                'description' => $data['description'],
                'unit' => $data['unit'],
                'planned_quantity' => round((float) $data['planned_quantity'], 3),
                'rate' => round((float) $data['rate'], 2),
                'budget_amount' => $budgetAmount,
                'measured_quantity' => 0,
                'certified_quantity' => 0,
                'certified_amount' => 0,
                'status' => $data['status'] ?? 'active',
                'specifications' => $data['specifications'] ?? [],
                'metadata' => $data['metadata'] ?? ['source' => 'construction_service'],
            ]);

            $this->auditLogger->record(
                $actor,
                'construction.boq_item.created',
                'Created BOQ item',
                $boqItem,
                [
                    'boq_code' => $boqItem->boq_code,
                    'project_id' => $boqItem->project_id,
                    'planned_quantity' => $boqItem->planned_quantity,
                    'budget_amount' => $boqItem->budget_amount,
                ],
                $request,
            );

            return $boqItem->load($this->boqItemRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function submitContractorMeasurement(array $data, User $actor, ?Request $request = null): ContractorMeasurement
    {
        return DB::transaction(function () use ($data, $actor, $request): ContractorMeasurement {
            $project = $this->activeProjectForActor((int) $data['project_id'], $actor);
            $vendor = $this->activeVendorForProject((int) $data['vendor_id'], $project);
            $lines = $this->normalizeMeasurementLines($data['lines'], $project);
            $measuredTotal = round(collect($lines)->sum('measured_amount'), 2);
            $certifiedTotal = round(collect($lines)->sum('certified_amount'), 2);

            $measurement = ContractorMeasurement::create([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'vendor_id' => $vendor->id,
                'submitted_by_user_id' => $actor->id,
                'measurement_number' => $this->nextMeasurementNumber(),
                'measurement_date' => $data['measurement_date'],
                'bill_reference' => $data['bill_reference'] ?? null,
                'status' => 'submitted',
                'measured_total' => $measuredTotal,
                'certified_total' => $certifiedTotal,
                'lines' => $lines,
                'remarks' => $data['remarks'] ?? null,
                'workflow_history' => [
                    $this->workflowEvent('submitted', $actor, 'Contractor measurement submitted'),
                ],
            ]);

            $this->auditLogger->record(
                $actor,
                'construction.contractor_measurement.submitted',
                'Submitted contractor measurement',
                $measurement,
                [
                    'measurement_number' => $measurement->measurement_number,
                    'project_id' => $measurement->project_id,
                    'vendor_id' => $measurement->vendor_id,
                    'certified_total' => $measurement->certified_total,
                ],
                $request,
            );

            return $measurement->load($this->measurementRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function approveContractorMeasurement(ContractorMeasurement $contractorMeasurement, User $actor, array $data = [], ?Request $request = null): ContractorMeasurement
    {
        return DB::transaction(function () use ($contractorMeasurement, $actor, $data, $request): ContractorMeasurement {
            $measurement = ContractorMeasurement::query()
                ->whereKey($contractorMeasurement->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCompanyScope($actor, $measurement->company_id, 'contractor_measurement');

            if ($measurement->status !== 'submitted') {
                throw ValidationException::withMessages(['contractor_measurement' => 'Only submitted contractor measurements can be approved.']);
            }

            if ($measurement->submitted_by_user_id === $actor->id) {
                throw ValidationException::withMessages(['contractor_measurement' => 'The measurement submitter cannot approve the same measurement.']);
            }

            $this->applyMeasurementToBoq($measurement);

            $history = $measurement->workflow_history ?? [];
            $history[] = $this->workflowEvent('approved', $actor, $data['note'] ?? 'Contractor measurement certified');

            $measurement->forceFill([
                'status' => 'approved',
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'construction.contractor_measurement.approved',
                'Approved contractor measurement',
                $measurement,
                [
                    'measurement_number' => $measurement->measurement_number,
                    'certified_total' => $measurement->certified_total,
                    'note' => $data['note'] ?? null,
                ],
                $request,
            );

            return $measurement->load($this->measurementRelations());
        });
    }

    public function rejectContractorMeasurement(ContractorMeasurement $contractorMeasurement, string $reason, User $actor, ?Request $request = null): ContractorMeasurement
    {
        return DB::transaction(function () use ($contractorMeasurement, $reason, $actor, $request): ContractorMeasurement {
            $measurement = ContractorMeasurement::query()
                ->whereKey($contractorMeasurement->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCompanyScope($actor, $measurement->company_id, 'contractor_measurement');

            if ($measurement->status !== 'submitted') {
                throw ValidationException::withMessages(['contractor_measurement' => 'Only submitted contractor measurements can be rejected.']);
            }

            if ($measurement->submitted_by_user_id === $actor->id) {
                throw ValidationException::withMessages(['contractor_measurement' => 'The measurement submitter cannot reject the same measurement.']);
            }

            $history = $measurement->workflow_history ?? [];
            $history[] = $this->workflowEvent('rejected', $actor, $reason);

            $measurement->forceFill([
                'status' => 'rejected',
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'construction.contractor_measurement.rejected',
                'Rejected contractor measurement',
                $measurement,
                ['measurement_number' => $measurement->measurement_number, 'reason' => $reason],
                $request,
            );

            return $measurement->load($this->measurementRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createContractorBill(array $data, User $actor, ?Request $request = null): ContractorBill
    {
        return DB::transaction(function () use ($data, $actor, $request): ContractorBill {
            $measurement = ContractorMeasurement::query()
                ->whereKey($data['contractor_measurement_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCompanyScope($actor, $measurement->company_id, 'contractor_measurement_id');

            if ($measurement->status !== 'approved') {
                throw ValidationException::withMessages(['contractor_measurement_id' => 'Only approved contractor measurements can be billed.']);
            }

            if (ContractorBill::query()->where('contractor_measurement_id', $measurement->id)->exists()) {
                throw ValidationException::withMessages(['contractor_measurement_id' => 'A contractor bill already exists for this measurement.']);
            }

            $rules = $this->contractorBillingRules((int) $measurement->company_id);
            $grossAmount = round((float) $measurement->certified_total, 2);
            $retentionPercent = round(array_key_exists('retention_percent', $data)
                ? (float) $data['retention_percent']
                : (float) $rules['default_retention_percent'], 2);

            if ($retentionPercent > (float) $rules['max_retention_percent']) {
                throw ValidationException::withMessages(['retention_percent' => 'Retention percent exceeds the configured contractor billing limit.']);
            }

            $deductions = $this->normalizeBillDeductions($data['deductions'] ?? []);
            $deductionAmount = round(collect($deductions)->sum('amount'), 2);
            $maxDeductionAmount = round($grossAmount * (float) $rules['max_deduction_percent_of_gross'] / 100, 2);

            if ($deductionAmount > $maxDeductionAmount) {
                throw ValidationException::withMessages(['deductions' => 'Total deductions exceed the configured contractor billing limit.']);
            }

            $retentionAmount = round($grossAmount * $retentionPercent / 100, 2);
            $taxAmount = round((float) ($data['tax_amount'] ?? 0), 2);
            $payableAmount = round($grossAmount - $retentionAmount - $deductionAmount + $taxAmount, 2);

            if ($payableAmount < 0) {
                throw ValidationException::withMessages(['deductions' => 'Retention and deductions cannot make contractor bill payable amount negative.']);
            }

            $bill = ContractorBill::create([
                'company_id' => $measurement->company_id,
                'project_id' => $measurement->project_id,
                'vendor_id' => $measurement->vendor_id,
                'contractor_measurement_id' => $measurement->id,
                'prepared_by_user_id' => $actor->id,
                'bill_number' => $this->nextContractorBillNumber(),
                'bill_date' => $data['bill_date'],
                'status' => 'submitted',
                'gross_amount' => $grossAmount,
                'retention_percent' => $retentionPercent,
                'retention_amount' => $retentionAmount,
                'deduction_amount' => $deductionAmount,
                'tax_amount' => $taxAmount,
                'payable_amount' => $payableAmount,
                'paid_amount' => 0,
                'balance_amount' => $payableAmount,
                'deductions' => $deductions,
                'payment_history' => [],
                'workflow_history' => [
                    $this->workflowEvent('submitted', $actor, 'Contractor bill submitted'),
                ],
                'remarks' => $data['remarks'] ?? null,
            ]);

            $this->auditLogger->record(
                $actor,
                'construction.contractor_bill.submitted',
                'Submitted contractor bill',
                $bill,
                [
                    'bill_number' => $bill->bill_number,
                    'measurement_number' => $measurement->measurement_number,
                    'gross_amount' => $bill->gross_amount,
                    'retention_amount' => $bill->retention_amount,
                    'deduction_amount' => $bill->deduction_amount,
                    'payable_amount' => $bill->payable_amount,
                ],
                $request,
            );

            return $bill->load($this->contractorBillRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function approveContractorBill(ContractorBill $contractorBill, User $actor, array $data = [], ?Request $request = null): ContractorBill
    {
        return DB::transaction(function () use ($contractorBill, $actor, $data, $request): ContractorBill {
            $bill = ContractorBill::query()->whereKey($contractorBill->id)->lockForUpdate()->firstOrFail();

            $this->assertCompanyScope($actor, $bill->company_id, 'contractor_bill');

            if ($bill->status !== 'submitted') {
                throw ValidationException::withMessages(['contractor_bill' => 'Only submitted contractor bills can be approved.']);
            }

            if ($bill->prepared_by_user_id === $actor->id) {
                throw ValidationException::withMessages(['contractor_bill' => 'The bill preparer cannot approve the same bill.']);
            }

            $history = $bill->workflow_history ?? [];
            $history[] = $this->workflowEvent('approved', $actor, $data['note'] ?? 'Contractor bill approved');

            $bill->forceFill([
                'status' => 'approved',
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'construction.contractor_bill.approved',
                'Approved contractor bill',
                $bill,
                [
                    'bill_number' => $bill->bill_number,
                    'payable_amount' => $bill->payable_amount,
                    'note' => $data['note'] ?? null,
                ],
                $request,
            );

            return $bill->load($this->contractorBillRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function markContractorBillPaid(ContractorBill $contractorBill, User $actor, array $data, ?Request $request = null): ContractorBill
    {
        return DB::transaction(function () use ($contractorBill, $actor, $data, $request): ContractorBill {
            $bill = ContractorBill::query()->whereKey($contractorBill->id)->lockForUpdate()->firstOrFail();

            $this->assertCompanyScope($actor, $bill->company_id, 'contractor_bill');

            if (! in_array($bill->status, ['approved', 'partially_paid'], true)) {
                throw ValidationException::withMessages(['contractor_bill' => 'Only approved or partially paid contractor bills can receive payments.']);
            }

            $paidNow = round((float) $data['paid_amount'], 2);
            $currentBalance = round((float) $bill->balance_amount, 2);

            if ($paidNow <= 0 || $paidNow > $currentBalance) {
                throw ValidationException::withMessages(['paid_amount' => 'Paid amount must be greater than zero and cannot exceed current bill balance.']);
            }

            $newPaidAmount = round((float) $bill->paid_amount + $paidNow, 2);
            $newBalance = round((float) $bill->payable_amount - $newPaidAmount, 2);
            $paymentHistory = $bill->payment_history ?? [];
            $paymentHistory[] = [
                'paid_amount' => $paidNow,
                'paid_on' => $data['paid_on'],
                'payment_reference' => $data['payment_reference'],
                'actor' => $actor->name,
                'note' => $data['note'] ?? null,
                'at' => now()->toISOString(),
            ];

            $history = $bill->workflow_history ?? [];
            $history[] = $this->workflowEvent($newBalance <= 0 ? 'paid' : 'partially_paid', $actor, $data['note'] ?? 'Contractor bill payment recorded');

            $bill->forceFill([
                'status' => $newBalance <= 0 ? 'paid' : 'partially_paid',
                'paid_by_user_id' => $actor->id,
                'paid_amount' => $newPaidAmount,
                'balance_amount' => max($newBalance, 0),
                'payment_history' => $paymentHistory,
                'workflow_history' => $history,
                'paid_at' => $newBalance <= 0 ? now() : $bill->paid_at,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'construction.contractor_bill.payment_recorded',
                'Recorded contractor bill payment',
                $bill,
                [
                    'bill_number' => $bill->bill_number,
                    'paid_amount' => $paidNow,
                    'total_paid_amount' => $bill->paid_amount,
                    'balance_amount' => $bill->balance_amount,
                    'payment_reference' => $data['payment_reference'],
                ],
                $request,
            );

            return $bill->load($this->contractorBillRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function submitDailyReport(array $data, User $actor, ?Request $request = null): DailyProgressReport
    {
        return DB::transaction(function () use ($data, $actor, $request): DailyProgressReport {
            $project = $this->activeProjectForActor((int) $data['project_id'], $actor);

            $report = DailyProgressReport::create([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'prepared_by_user_id' => $actor->id,
                'report_number' => $this->nextReportNumber(),
                'report_date' => $data['report_date'],
                'weather' => $data['weather'] ?? null,
                'manpower_count' => $data['manpower_count'],
                'manpower_breakup' => $data['manpower_breakup'] ?? [],
                'progress_items' => $this->normalizeProgressItems($data['progress_items'], $project),
                'materials_used' => $data['materials_used'] ?? [],
                'equipment_used' => $data['equipment_used'] ?? [],
                'work_summary' => $data['work_summary'],
                'safety_observations' => $data['safety_observations'] ?? null,
                'quality_observations' => $data['quality_observations'] ?? null,
                'blockers' => $data['blockers'] ?? null,
                'status' => 'submitted',
                'workflow_history' => [
                    $this->workflowEvent('submitted', $actor, 'Daily progress report submitted'),
                ],
            ]);

            $this->auditLogger->record(
                $actor,
                'construction.daily_report.submitted',
                'Submitted daily progress report',
                $report,
                [
                    'report_number' => $report->report_number,
                    'project_id' => $report->project_id,
                    'report_date' => $report->report_date?->toDateString(),
                ],
                $request,
            );

            return $report->load($this->dailyReportRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function approveDailyReport(DailyProgressReport $dailyProgressReport, User $actor, array $data = [], ?Request $request = null): DailyProgressReport
    {
        return DB::transaction(function () use ($dailyProgressReport, $actor, $data, $request): DailyProgressReport {
            $report = DailyProgressReport::query()->whereKey($dailyProgressReport->id)->lockForUpdate()->firstOrFail();

            $this->assertCompanyScope($actor, $report->company_id, 'daily_progress_report');

            if ($report->status !== 'submitted') {
                throw ValidationException::withMessages(['daily_progress_report' => 'Only submitted daily progress reports can be approved.']);
            }

            if ($report->prepared_by_user_id === $actor->id) {
                throw ValidationException::withMessages(['daily_progress_report' => 'The report preparer cannot approve the same report.']);
            }

            foreach ($report->progress_items ?? [] as $progressItem) {
                $this->applyMilestoneProgress($progressItem, $report);
            }

            $history = $report->workflow_history ?? [];
            $history[] = $this->workflowEvent('approved', $actor, $data['note'] ?? 'Daily progress report approved');

            $report->forceFill([
                'status' => 'approved',
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'construction.daily_report.approved',
                'Approved daily progress report',
                $report,
                [
                    'report_number' => $report->report_number,
                    'project_id' => $report->project_id,
                    'note' => $data['note'] ?? null,
                ],
                $request,
            );

            return $report->load($this->dailyReportRelations());
        });
    }

    public function rejectDailyReport(DailyProgressReport $dailyProgressReport, string $reason, User $actor, ?Request $request = null): DailyProgressReport
    {
        return DB::transaction(function () use ($dailyProgressReport, $reason, $actor, $request): DailyProgressReport {
            $report = DailyProgressReport::query()->whereKey($dailyProgressReport->id)->lockForUpdate()->firstOrFail();

            $this->assertCompanyScope($actor, $report->company_id, 'daily_progress_report');

            if ($report->status !== 'submitted') {
                throw ValidationException::withMessages(['daily_progress_report' => 'Only submitted daily progress reports can be rejected.']);
            }

            if ($report->prepared_by_user_id === $actor->id) {
                throw ValidationException::withMessages(['daily_progress_report' => 'The report preparer cannot reject the same report.']);
            }

            $history = $report->workflow_history ?? [];
            $history[] = $this->workflowEvent('rejected', $actor, $reason);

            $report->forceFill([
                'status' => 'rejected',
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'construction.daily_report.rejected',
                'Rejected daily progress report',
                $report,
                ['report_number' => $report->report_number, 'reason' => $reason],
                $request,
            );

            return $report->load($this->dailyReportRelations());
        });
    }

    /**
     * @param array<int, array<string, mixed>> $progressItems
     * @return array<int, array<string, mixed>>
     */
    private function normalizeProgressItems(array $progressItems, Project $project): array
    {
        return collect($progressItems)->map(function (array $item) use ($project): array {
            $milestone = ConstructionMilestone::query()
                ->whereKey($item['milestone_id'])
                ->where('company_id', $project->company_id)
                ->where('project_id', $project->id)
                ->firstOrFail();

            return [
                'milestone_id' => $milestone->id,
                'milestone_code' => $milestone->milestone_code,
                'milestone_name' => $milestone->name,
                'work_done' => $item['work_done'],
                'progress_percent' => round((float) $item['progress_percent'], 2),
            ];
        })->values()->all();
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @return array<int, array<string, mixed>>
     */
    private function normalizeMeasurementLines(array $lines, Project $project): array
    {
        return collect($lines)->map(function (array $line) use ($project): array {
            $boqItem = BoqItem::query()
                ->whereKey($line['boq_item_id'])
                ->where('company_id', $project->company_id)
                ->where('project_id', $project->id)
                ->where('status', 'active')
                ->firstOrFail();

            $measuredQuantity = round((float) $line['measured_quantity'], 3);
            $certifiedQuantity = array_key_exists('certified_quantity', $line)
                ? round((float) $line['certified_quantity'], 3)
                : $measuredQuantity;

            return [
                'boq_item_id' => $boqItem->id,
                'boq_code' => $boqItem->boq_code,
                'description' => $boqItem->description,
                'trade' => $boqItem->trade,
                'unit' => $boqItem->unit,
                'rate' => (float) $boqItem->rate,
                'planned_quantity' => (float) $boqItem->planned_quantity,
                'previous_certified_quantity' => (float) $boqItem->certified_quantity,
                'measured_quantity' => $measuredQuantity,
                'certified_quantity' => $certifiedQuantity,
                'measured_amount' => round($measuredQuantity * (float) $boqItem->rate, 2),
                'certified_amount' => round($certifiedQuantity * (float) $boqItem->rate, 2),
                'remarks' => $line['remarks'] ?? null,
            ];
        })->values()->all();
    }

    private function applyMeasurementToBoq(ContractorMeasurement $measurement): void
    {
        foreach ($measurement->lines ?? [] as $index => $line) {
            $boqItem = BoqItem::query()
                ->whereKey($line['boq_item_id'])
                ->where('company_id', $measurement->company_id)
                ->where('project_id', $measurement->project_id)
                ->lockForUpdate()
                ->firstOrFail();

            $newCertifiedQuantity = round((float) $boqItem->certified_quantity + (float) $line['certified_quantity'], 3);
            if ($newCertifiedQuantity > round((float) $boqItem->planned_quantity, 3)) {
                throw ValidationException::withMessages([
                    "lines.$index.certified_quantity" => "Certified quantity for BOQ {$boqItem->boq_code} exceeds planned BOQ quantity.",
                ]);
            }

            $newMeasuredQuantity = round((float) $boqItem->measured_quantity + (float) $line['measured_quantity'], 3);

            $boqItem->forceFill([
                'measured_quantity' => $newMeasuredQuantity,
                'certified_quantity' => $newCertifiedQuantity,
                'certified_amount' => round($newCertifiedQuantity * (float) $boqItem->rate, 2),
                'status' => $newCertifiedQuantity >= round((float) $boqItem->planned_quantity, 3) ? 'closed' : $boqItem->status,
                'metadata' => array_merge($boqItem->metadata ?? [], [
                    'last_measurement_number' => $measurement->measurement_number,
                    'last_measurement_date' => $measurement->measurement_date?->toDateString(),
                ]),
            ])->save();
        }
    }

    /**
     * @param array<string, mixed> $progressItem
     */
    private function applyMilestoneProgress(array $progressItem, DailyProgressReport $report): void
    {
        $milestone = ConstructionMilestone::query()
            ->whereKey($progressItem['milestone_id'])
            ->where('company_id', $report->company_id)
            ->where('project_id', $report->project_id)
            ->lockForUpdate()
            ->firstOrFail();

        $progress = max((float) $milestone->progress_percent, (float) $progressItem['progress_percent']);
        $status = match (true) {
            $progress >= 100 => 'completed',
            $progress > 0 => 'in_progress',
            default => 'planned',
        };

        $milestone->forceFill([
            'progress_percent' => min($progress, 100),
            'status' => $status,
            'actual_start_on' => $milestone->actual_start_on ?? $report->report_date,
            'actual_end_on' => $status === 'completed' ? ($milestone->actual_end_on ?? $report->report_date) : $milestone->actual_end_on,
            'metadata' => array_merge($milestone->metadata ?? [], [
                'last_progress_report_number' => $report->report_number,
                'last_progress_report_date' => $report->report_date?->toDateString(),
            ]),
        ])->save();
    }

    /**
     * @return array<string, string>
     */
    private function workflowEvent(string $status, User $actor, string $note): array
    {
        return [
            'status' => $status,
            'actor' => $actor->name,
            'note' => $note,
            'at' => now()->toISOString(),
        ];
    }

    private function activeProjectForActor(int $projectId, User $actor): Project
    {
        $project = Project::query()->whereKey($projectId)->firstOrFail();

        $this->assertCompanyScope($actor, $project->company_id, 'project_id');

        if ($project->status !== 'active') {
            throw ValidationException::withMessages(['project_id' => 'The selected project is not active for your company.']);
        }

        return $project;
    }

    private function activeVendorForProject(int $vendorId, Project $project): Vendor
    {
        $vendor = Vendor::query()->whereKey($vendorId)->firstOrFail();

        if ($vendor->company_id !== $project->company_id || $vendor->status !== 'active') {
            throw ValidationException::withMessages(['vendor_id' => 'The vendor must be active for your company.']);
        }

        return $vendor;
    }

    private function assertOptionalVendorScope(mixed $vendorId, Project $project): void
    {
        if ((int) $vendorId <= 0) {
            return;
        }

        $this->activeVendorForProject((int) $vendorId, $project);
    }

    private function assertOptionalMilestoneScope(mixed $milestoneId, Project $project): void
    {
        if ((int) $milestoneId <= 0) {
            return;
        }

        $valid = ConstructionMilestone::query()
            ->whereKey((int) $milestoneId)
            ->where('company_id', $project->company_id)
            ->where('project_id', $project->id)
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages(['construction_milestone_id' => 'The milestone must belong to the selected project.']);
        }
    }

    private function assertCompanyScope(User $actor, int|string|null $companyId, string $field): void
    {
        if ($this->companyScope->allows($actor, $companyId)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => 'The selected record is outside your company scope.',
        ]);
    }

    private function nextReportNumber(): string
    {
        return sprintf('DPR-%04d', DailyProgressReport::query()->withTrashed()->count() + 1001);
    }

    private function nextMeasurementNumber(): string
    {
        return sprintf('MB-%05d', ContractorMeasurement::query()->withTrashed()->count() + 10001);
    }

    private function nextContractorBillNumber(): string
    {
        return sprintf('CB-%s-%05d', now()->format('Y'), ContractorBill::query()->withTrashed()->count() + 10001);
    }

    /**
     * @param array<int, array<string, mixed>> $deductions
     * @return array<int, array{code: string, description: string, amount: float}>
     */
    private function normalizeBillDeductions(array $deductions): array
    {
        return collect($deductions)->map(fn (array $deduction): array => [
            'code' => strtoupper((string) $deduction['code']),
            'description' => (string) $deduction['description'],
            'amount' => round((float) $deduction['amount'], 2),
        ])->values()->all();
    }

    /**
     * @return array{default_retention_percent: float, max_retention_percent: float, max_deduction_percent_of_gross: float}
     */
    private function contractorBillingRules(int $companyId): array
    {
        $rules = $this->settings->value($companyId, 'construction.contractor_billing', [
            'default_retention_percent' => 5,
            'max_retention_percent' => 10,
            'max_deduction_percent_of_gross' => 30,
        ]);

        return [
            'default_retention_percent' => max(0, (float) ($rules['default_retention_percent'] ?? 5)),
            'max_retention_percent' => max(0, (float) ($rules['max_retention_percent'] ?? 10)),
            'max_deduction_percent_of_gross' => max(0, (float) ($rules['max_deduction_percent_of_gross'] ?? 30)),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function dailyReportRelations(): array
    {
        return ['project', 'preparedBy', 'approvedBy'];
    }

    /**
     * @return array<int, string>
     */
    public function boqItemRelations(): array
    {
        return ['project', 'milestone', 'vendor', 'createdBy'];
    }

    /**
     * @return array<int, string>
     */
    public function measurementRelations(): array
    {
        return ['project', 'vendor', 'submittedBy', 'approvedBy'];
    }

    /**
     * @return array<int, string>
     */
    public function contractorBillRelations(): array
    {
        return ['project', 'vendor', 'measurement', 'preparedBy', 'approvedBy', 'paidBy'];
    }
}
