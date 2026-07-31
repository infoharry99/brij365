<?php

namespace App\Domain\Payroll\Services;

use App\Domain\Payroll\Data\PayrollCalculationResult;
use App\Models\PayrollCalculationSnapshot;
use App\Models\PayrollRunItem;
use App\Models\SalaryAssignment;
use App\Models\User;

final class PayrollCalculationSnapshotWriter
{
    public function write(
        PayrollRunItem $runItem,
        SalaryAssignment $assignment,
        User $actor,
        PayrollCalculationResult $result,
    ): PayrollCalculationSnapshot {
        $snapshot = PayrollCalculationSnapshot::create([
            'payroll_run_item_id' => $runItem->id,
            'payroll_attendance_snapshot_id' => $result->attendanceSnapshotId,
            'salary_assignment_id' => $assignment->id,
            'company_id' => $runItem->company_id,
            'employee_id' => $runItem->employee_id,
            'created_by_user_id' => $actor->id,
            'currency' => 'INR',
            'calculation_version' => StatutoryPayrollEngine::CALCULATION_VERSION,
            'gross_minor' => $result->gross->minor,
            'deduction_minor' => $result->deductions->minor,
            'employer_contribution_minor' => $result->employerContributions->minor,
            'net_minor' => $result->net->minor,
            'input_hash' => $result->inputHash,
            'result_hash' => $result->resultHash,
            'rule_context' => $result->ruleContext,
            'input_snapshot' => $result->inputSnapshot,
            'calculation_trace' => $result->trace,
        ]);

        $snapshot->lines()->createMany($result->calculationLines);

        return $snapshot->load('lines');
    }
}
