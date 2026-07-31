<?php

namespace App\Domain\Hr\Services;

use App\Models\Employee;
use App\Models\EmployeeTaxDocument;
use App\Models\PayrollRunItem;
use App\Models\SalaryAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class EmployeePayrollSummary
{
    public function forEmployee(Employee $employee, User $actor): array
    {
        $selfService = $employee->user_id === $actor->id && $actor->hasPermission('employee.self_service');
        $internal = ! $selfService || $actor->hasPermission('payroll.view') || $actor->hasPermission('payroll.manage') || $actor->hasPermission('payroll.approve') || $actor->hasPermission('hr.manage') || $actor->hasPermission('*');
        $assignment = SalaryAssignment::query()->with(['salaryStructure.components.payrollComponent'])->where('company_id', $employee->company_id)->where('employee_id', $employee->id)->where('status', 'active')->whereDate('effective_from', '<=', now()->toDateString())->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', now()->toDateString()))->latest('effective_from')->first();
        $items = PayrollRunItem::query()->with(['payrollRun', 'salaryStructure'])->where('company_id', $employee->company_id)->where('employee_id', $employee->id)->when($selfService && ! $internal, fn (Builder $q) => $q->where('status', 'approved')->whereHas('payrollRun', fn (Builder $r) => $r->where('status', 'approved')))->latest('id')->limit(12)->get();
        $documents = EmployeeTaxDocument::query()->with(['generatedBy:id,name,email', 'issuedBy:id,name,email', 'acknowledgedBy:id,name,email'])->where('company_id', $employee->company_id)->where('employee_id', $employee->id)->when($selfService && ! $internal, fn (Builder $q) => $q->whereIn('status', ['issued', 'acknowledged']))->latest('generated_at')->latest('id')->limit(8)->get();

        return [
            'employee' => ['id' => $employee->id, 'employee_code' => $employee->employee_code, 'name' => $employee->name, 'designation' => $employee->designation, 'department' => $employee->department, 'company_id' => $employee->company_id],
            'access_mode' => $selfService && ! $internal ? 'self_service' : 'internal_payroll',
            'current_assignment' => $assignment ? $this->assignment($assignment) : null,
            'payroll_items' => $items->map(fn (PayrollRunItem $item) => $this->item($item, $internal))->values(),
            'tax_documents' => $documents->map(fn (EmployeeTaxDocument $document) => $this->document($document, $internal))->values(),
            'totals' => ['payroll_items_count' => $items->count(), 'tax_documents_count' => $documents->count(), 'gross_earnings' => round((float) $items->sum('gross_earnings'), 2), 'total_deductions' => round((float) $items->sum('total_deductions'), 2), 'net_payable' => round((float) $items->sum('net_payable'), 2)],
        ];
    }

    private function assignment(SalaryAssignment $a): array
    {
        return ['id' => $a->id, 'effective_from' => $a->effective_from?->toDateString(), 'effective_to' => $a->effective_to?->toDateString(), 'status' => $a->status, 'structure' => $a->salaryStructure ? ['id' => $a->salaryStructure->id, 'code' => $a->salaryStructure->code, 'name' => $a->salaryStructure->name, 'version' => $a->salaryStructure->version, 'monthly_ctc' => (float) $a->salaryStructure->monthly_ctc, 'components' => $a->salaryStructure->components->map(fn ($c) => ['component_code' => $c->payrollComponent?->code, 'component_name' => $c->payrollComponent?->name, 'component_type' => $c->payrollComponent?->component_type, 'amount' => (float) $c->amount, 'percentage_of_ctc' => (float) $c->percentage_of_ctc])->values()] : null];
    }

    private function item(PayrollRunItem $i, bool $internal): array
    {
        return ['id' => $i->id, 'status' => $i->status, 'period_year' => $i->payrollRun?->period_year, 'period_month' => $i->payrollRun?->period_month, 'run_number' => $i->payrollRun?->run_number, 'run_status' => $i->payrollRun?->status, 'working_days' => $i->payrollRun?->working_days, 'payable_days' => $i->payable_days, 'monthly_ctc' => (float) $i->monthly_ctc, 'gross_earnings' => (float) $i->gross_earnings, 'total_deductions' => (float) $i->total_deductions, 'net_payable' => (float) $i->net_payable, 'salary_structure' => $i->salaryStructure ? ['code' => $i->salaryStructure->code, 'name' => $i->salaryStructure->name, 'version' => $i->salaryStructure->version] : null, 'component_breakup' => $internal ? ($i->component_breakup ?? []) : []];
    }

    private function document(EmployeeTaxDocument $d, bool $internal): array
    {
        return ['id' => $d->id, 'document_number' => $d->document_number, 'document_type' => $d->document_type, 'financial_year' => $d->financial_year, 'assessment_year' => $d->assessment_year, 'version' => $d->version, 'status' => $d->status, 'gross_salary' => (float) $d->gross_salary, 'taxable_income' => (float) $d->taxable_income, 'tds_deducted' => (float) $d->tds_deducted, 'net_salary_paid' => (float) $d->net_salary_paid, 'payroll_run_ids' => $internal ? ($d->payroll_run_ids ?? []) : [], 'generated_at' => $d->generated_at?->toISOString(), 'issued_at' => $d->issued_at?->toISOString(), 'acknowledged_at' => $d->acknowledged_at?->toISOString(), 'generated_by' => $internal ? $this->user($d->generatedBy) : null, 'issued_by' => $this->user($d->issuedBy), 'acknowledged_by' => $this->user($d->acknowledgedBy)];
    }

    private function user($user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email] : null;
    }
}
