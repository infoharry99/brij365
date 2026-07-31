<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Domain\Hr\Services\EmployeeFieldVisibility;
use App\Domain\Hr\Services\EmployeeRegister;
use App\Models\Employee;
use App\Services\Audit\AuditLogger;
use App\Services\Governance\ManagementReportService;
use App\Services\Governance\ReportLimitPolicy;
use Symfony\Component\HttpFoundation\Response;

final class ExportEmployeeRegister
{
    public function __construct(private readonly EmployeeRegister $employees, private readonly EmployeeFieldVisibility $visibility, private readonly ManagementReportService $reports, private readonly ReportLimitPolicy $limits, private readonly AuditLogger $audit) {}

    public function execute(HrCommandData $command): Response
    {
        $format = ($command->attributes['format'] ?? 'csv') === 'xls' ? 'excel' : (string) ($command->attributes['format'] ?? 'csv');
        $reportType = (string) ($command->attributes['report_type'] ?? 'Employee Master Register');
        $filters = $command->attributes;
        unset($filters['format'], $filters['report_type']);
        $canViewCompensation = $this->visibility->canViewCompensation($command->actor);
        $rows = $this->employees->query($command->actor, $filters)->limit($this->limits->maxExportRows())->get()->map(fn (Employee $employee): array => [
            'employee_code' => $employee->employee_code, 'name' => $employee->name, 'designation' => $employee->designation, 'department' => $employee->department, 'grade' => $employee->grade, 'employment_type' => $employee->employment_type, 'status' => $employee->status, 'joined_on' => $employee->joined_on?->toDateString(), 'statutory_state' => $employee->statutory_state,
            'company_code' => $employee->company?->code, 'company_name' => $employee->company?->name, 'branch_code' => $employee->branch?->code, 'branch_name' => $employee->branch?->name, 'project_code' => $employee->project?->code, 'project_name' => $employee->project?->name, 'manager_code' => $employee->manager?->employee_code, 'manager_name' => $employee->manager?->name,
            'direct_reports_count' => (int) ($employee->direct_reports_count ?? 0), 'documents_count' => (int) ($employee->managed_documents_count ?? 0), 'monthly_ctc' => $canViewCompensation ? (float) $employee->monthly_ctc : 'restricted', 'user_email' => $employee->user?->email, 'user_status' => $employee->user?->status, 'exported_at' => now()->toDateTimeString(),
        ])->all();
        $this->audit->record($command->actor, 'hr.employee_report.exported', 'Exported HR employee MIS report', null, ['format' => $format, 'report_type' => $reportType, 'row_count' => count($rows), 'filters' => $filters, 'max_rows' => $this->limits->maxExportRows(), 'compensation_visible' => $canViewCompensation], $command->request);
        $filename = 'builder360-hr-employee-mis';
        if ($format === 'pdf') {
            return response($this->reports->pdf($rows, 'Builder360 HR MIS - '.$reportType), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        }
        if ($format === 'excel') {
            return response($this->reports->excelXml($rows, 'HR MIS'), 200, ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$filename.'.xls"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        }
        $headers = 'employee_code,name,designation,department,grade,employment_type,status,joined_on,statutory_state,company_code,company_name,branch_code,branch_name,project_code,project_name,manager_code,manager_name,direct_reports_count,documents_count,monthly_ctc,user_email,user_status,exported_at';

        return response($rows === [] ? $headers."\n" : $this->reports->csv($rows), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$filename.'.csv"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
