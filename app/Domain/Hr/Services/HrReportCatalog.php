<?php

namespace App\Domain\Hr\Services;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\User;

final class HrReportCatalog
{
    public function __construct(private readonly EmployeeFieldVisibility $fieldVisibility) {}

    /**
     * Return only reports backed by an existing, authorized export contract.
     *
     * @return array<int, array<string, mixed>>
     */
    public function for(User $actor): array
    {
        $reports = [];

        if (
            $actor->can('viewAny', Employee::class)
            && $this->fieldVisibility->canExportRegister($actor)
        ) {
            $reports[] = [
                'key' => 'employee-master-register',
                'title' => 'Employee Master Register',
                'description' => 'Company-scoped employee identity, placement, reporting, and status records.',
                'category' => 'Employee master',
                'icon' => 'fa-address-card',
                'routeName' => 'hr.employees.export',
                'routeParameters' => [
                    'report_type' => 'Employee Master Register',
                ],
                'reportType' => 'Employee Master Register',
                'formats' => [
                    'csv' => 'CSV',
                    'xls' => 'Excel',
                    'pdf' => 'PDF',
                ],
                'auditEvent' => 'hr.employee_report.exported',
            ];
        }

        if (
            $actor->can('reports.view')
            && $actor->can('viewAny', PayrollRun::class)
        ) {
            $reports[] = [
                'key' => 'payroll-run-register',
                'title' => 'Payroll Run Register',
                'description' => 'Company-scoped payroll periods, lifecycle status, gross earnings, deductions, and net payable totals.',
                'category' => 'Payroll',
                'icon' => 'fa-money-check-dollar',
                'routeName' => 'governance.report-register.index',
                'routeParameters' => [
                    'report' => 'payroll',
                ],
                'formats' => [
                    'csv' => 'CSV',
                    'excel' => 'Excel',
                    'pdf' => 'PDF',
                ],
                'auditEvent' => 'governance.report.exported',
            ];
        }

        return $reports;
    }
}
