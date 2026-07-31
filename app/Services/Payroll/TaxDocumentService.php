<?php

namespace App\Services\Payroll;

use App\Domain\Payroll\Data\StatutoryPayrollCutoverManifest;
use App\Domain\Payroll\Data\TaxDocumentPayrollSummary;
use App\Domain\Payroll\Services\GovernedTaxSettingVerifier;
use App\Domain\Payroll\Services\TaxDocumentPayrollSummarizer;
use App\Domain\Payroll\ValueObjects\MinorMoney;
use App\Models\Employee;
use App\Models\EmployeeTaxDocument;
use App\Models\PayrollRun;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationCenterService;
use App\Services\Security\CompanyScopeService;
use App\Services\Settings\SystemSettingResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaxDocumentService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationCenterService $notifications,
        private readonly SystemSettingResolver $settings,
        private readonly CompanyScopeService $companyScope,
        private readonly TaxDocumentPayrollSummarizer $payrollSummarizer,
        private readonly GovernedTaxSettingVerifier $taxSettingVerifier,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function generate(array $data, User $actor, ?Request $request = null): EmployeeTaxDocument
    {
        return DB::transaction(function () use ($data, $actor, $request): EmployeeTaxDocument {
            $employee = Employee::query()->whereKey($data['employee_id'])->lockForUpdate()->firstOrFail();
            $this->assertCompanyScope($actor, $employee->company_id, 'employee_id');

            $financialYear = $data['financial_year'];
            [$fyStart, $fyEnd] = $this->financialYearWindow($financialYear);
            $setting = $this->activeVerifiedTaxSetting($employee->company_id, $financialYear);

            $existing = EmployeeTaxDocument::query()
                ->where('employee_id', $employee->id)
                ->where('document_type', 'form_16')
                ->where('financial_year', $financialYear)
                ->whereIn('status', ['generated', 'issued', 'acknowledged'])
                ->orderByDesc('version')
                ->first();

            if ($existing && ! ($data['force_new_version'] ?? false)) {
                throw ValidationException::withMessages(['financial_year' => 'A Form 16 already exists for this employee and financial year. Use force_new_version to regenerate.']);
            }

            $payrollRuns = PayrollRun::query()
                ->with(['items' => fn ($query) => $query
                    ->where('employee_id', $employee->id)
                    ->with(['calculationSnapshot.lines'])])
                ->where('company_id', $employee->company_id)
                ->where('status', 'approved')
                ->whereDate('period_start', '>=', $fyStart->toDateString())
                ->whereDate('period_end', '<=', $fyEnd->toDateString())
                ->orderBy('period_year')
                ->orderBy('period_month')
                ->get()
                ->filter(fn (PayrollRun $run): bool => $run->items->isNotEmpty())
                ->values();

            if ($payrollRuns->isEmpty()) {
                throw ValidationException::withMessages(['financial_year' => 'No approved payroll runs were found for this employee and financial year.']);
            }

            $version = (int) EmployeeTaxDocument::query()
                ->where('employee_id', $employee->id)
                ->where('document_type', 'form_16')
                ->where('financial_year', $financialYear)
                ->max('version') + 1;

            $summary = $this->payrollSummarizer->summarize($payrollRuns, $setting);
            $taxConfig = $setting->value ?? [];
            $taxGovernance = $summary->calculationMode === StatutoryPayrollCutoverManifest::MODE_GOVERNED_REQUIRED
                ? $this->taxSettingVerifier->assertVerified($setting, 'financial_year')
                : [];
            $templateStatus = strtolower(trim((string) ($taxConfig['form16_template_status'] ?? 'prototype')));
            $legalTemplateApproved = ($taxConfig['legal_template_approved'] ?? false) === true;
            $isPrototype = ($taxConfig['is_prototype'] ?? false) === true || $templateStatus !== 'approved';
            $grossSalary = MinorMoney::fromMinor($summary->grossMinor)->toDecimal();
            $taxableIncome = MinorMoney::fromMinor($summary->taxableIncomeMinor)->toDecimal();
            $tdsDeducted = MinorMoney::fromMinor($summary->tdsMinor)->toDecimal();
            $netSalaryPaid = MinorMoney::fromMinor($summary->netMinor)->toDecimal();

            $document = EmployeeTaxDocument::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'generated_by_user_id' => $actor->id,
                'document_number' => $this->nextDocumentNumber(),
                'document_type' => 'form_16',
                'financial_year' => $financialYear,
                'assessment_year' => $this->assessmentYear($financialYear),
                'version' => $version,
                'status' => 'generated',
                'gross_salary' => $grossSalary,
                'taxable_income' => $taxableIncome,
                'tds_deducted' => $tdsDeducted,
                'net_salary_paid' => $netSalaryPaid,
                'payroll_run_ids' => $payrollRuns->pluck('id')->all(),
                'component_summary' => $summary->componentSummary,
                'tax_configuration_snapshot' => [
                    'setting_id' => $setting->id,
                    'setting_key' => $setting->setting_key,
                    'version' => $setting->version,
                    'verified' => $taxConfig['verified'] ?? false,
                    'financial_year' => $financialYear,
                    'payroll_year_locked' => $taxConfig['payroll_year_locked'] ?? false,
                    'form16_template_version' => $taxConfig['form16_template_version'] ?? 'v1',
                    'form16_template_status' => $templateStatus,
                    'legal_template_approved' => $legalTemplateApproved,
                    'is_prototype' => $isPrototype,
                    'calculation_mode' => $summary->calculationMode,
                    'payroll_calculation_provenance' => $summary->provenance,
                ] + $taxGovernance,
                'document_payload' => $this->payload($employee, $payrollRuns, $summary, $taxableIncome, $setting),
                'workflow_history' => [
                    $this->workflowEvent('generated', $actor, 'Form 16 generated from approved payroll data.'),
                ],
                'generated_at' => now(),
            ]);

            $this->auditLogger->record($actor, 'payroll.tax_document.generated', 'Generated employee tax document', $document, [
                'document_number' => $document->document_number,
                'financial_year' => $financialYear,
                'employee_code' => $employee->employee_code,
                'gross_salary' => $document->gross_salary,
                'tds_deducted' => $document->tds_deducted,
            ], $request);

            return $document->load($this->relations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function issue(EmployeeTaxDocument $employeeTaxDocument, array $data, User $actor, ?Request $request = null): EmployeeTaxDocument
    {
        return DB::transaction(function () use ($employeeTaxDocument, $data, $actor, $request): EmployeeTaxDocument {
            $document = EmployeeTaxDocument::query()->whereKey($employeeTaxDocument->id)->lockForUpdate()->firstOrFail();
            $this->assertCompanyScope($actor, $document->company_id, 'tax_document');
            $this->taxSettingVerifier->assertIssuable((array) $document->tax_configuration_snapshot, 'tax_document');

            $history = $document->workflow_history ?? [];
            $history[] = $this->workflowEvent('issued', $actor, $data['note'] ?? 'Form 16 issued to employee.');

            $document->forceFill([
                'status' => 'issued',
                'issued_by_user_id' => $actor->id,
                'issued_at' => now(),
                'issue_reference' => $data['issue_reference'] ?? sprintf('ISSUE-%s', $document->document_number),
                'workflow_history' => $history,
            ])->save();

            if ($document->employee?->user) {
                $this->notifications->sendToUser($document->employee->user, [
                    'category' => 'payroll',
                    'severity' => 'info',
                    'title' => 'Form 16 issued',
                    'body' => "Your Form 16 for {$document->financial_year} has been issued for acknowledgement.",
                    'action_url' => route('payroll.tax-documents.index', ['financial_year' => $document->financial_year], false),
                    'payload' => ['document_number' => $document->document_number],
                ], $actor, $document);
            }

            $this->auditLogger->record($actor, 'payroll.tax_document.issued', 'Issued employee tax document', $document, [
                'document_number' => $document->document_number,
                'financial_year' => $document->financial_year,
            ], $request);

            return $document->load($this->relations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function acknowledge(EmployeeTaxDocument $employeeTaxDocument, array $data, User $actor, ?Request $request = null): EmployeeTaxDocument
    {
        return DB::transaction(function () use ($employeeTaxDocument, $data, $actor, $request): EmployeeTaxDocument {
            $document = EmployeeTaxDocument::query()->whereKey($employeeTaxDocument->id)->lockForUpdate()->firstOrFail();
            $history = $document->workflow_history ?? [];
            $history[] = $this->workflowEvent('acknowledged', $actor, 'Employee acknowledged tax document.');

            $document->forceFill([
                'status' => 'acknowledged',
                'acknowledged_by_user_id' => $actor->id,
                'acknowledged_at' => now(),
                'employee_acknowledgement_note' => $data['employee_acknowledgement_note'] ?? null,
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record($actor, 'payroll.tax_document.acknowledged', 'Acknowledged employee tax document', $document, [
                'document_number' => $document->document_number,
                'financial_year' => $document->financial_year,
            ], $request);

            return $document->load($this->relations());
        });
    }

    /**
     * @return array<int, string>
     */
    public function relations(): array
    {
        return ['employee.user', 'generatedBy', 'issuedBy', 'acknowledgedBy'];
    }

    private function activeVerifiedTaxSetting(int $companyId, string $financialYear): SystemSetting
    {
        $setting = $this->settings->active($companyId, 'payroll.tax_rules');

        $value = $setting?->value ?? [];

        if (! $setting || ($value['financial_year'] ?? null) !== $financialYear) {
            throw ValidationException::withMessages(['financial_year' => 'An active payroll.tax_rules setting for this financial year is required before Form 16 generation.']);
        }

        if (($value['verified'] ?? false) !== true) {
            throw ValidationException::withMessages(['financial_year' => 'Tax configuration must be verified before Form 16 generation.']);
        }

        if (($value['payroll_year_locked'] ?? false) !== true) {
            throw ValidationException::withMessages(['financial_year' => 'Payroll year must be locked before Form 16 generation.']);
        }

        return $setting;
    }

    private function payload(Employee $employee, $payrollRuns, TaxDocumentPayrollSummary $summary, string $taxableIncome, SystemSetting $setting): array
    {
        return [
            'employee' => [
                'employee_code' => $employee->employee_code,
                'name' => $employee->name,
                'designation' => $employee->designation,
                'department' => $employee->department,
                'statutory_state' => $employee->statutory_state,
            ],
            'payroll_periods' => $summary->periods,
            'summary' => [
                'gross_salary' => MinorMoney::fromMinor($summary->grossMinor)->toDecimal(),
                'taxable_income' => $taxableIncome,
                'tds_deducted' => MinorMoney::fromMinor($summary->tdsMinor)->toDecimal(),
                'net_salary_paid' => MinorMoney::fromMinor($summary->netMinor)->toDecimal(),
                'component_summary' => $summary->componentSummary,
            ],
            'calculation_mode' => $summary->calculationMode,
            'tax_setting' => [
                'id' => $setting->id,
                'version' => $setting->version,
                'payroll_calculation_provenance' => $summary->provenance,
            ],
            'disclaimer' => $summary->calculationMode === StatutoryPayrollCutoverManifest::MODE_GOVERNED_REQUIRED
                && data_get($setting->value, 'form16_template_status') === 'approved'
                && data_get($setting->value, 'legal_template_approved') === true
                    ? 'Generated from independently verified, version-pinned payroll evidence. Verify employee identity before issue.'
                    : 'Prototype-generated Form 16 data must be validated by the client/statutory expert before production issue.',
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function financialYearWindow(string $financialYear): array
    {
        [$startYear, $endYear] = array_map('intval', explode('-', $financialYear));

        if ($endYear !== $startYear + 1) {
            throw ValidationException::withMessages(['financial_year' => 'Financial year must be in YYYY-YYYY format with consecutive years.']);
        }

        return [Carbon::create($startYear, 4, 1)->startOfDay(), Carbon::create($endYear, 3, 31)->endOfDay()];
    }

    private function assessmentYear(string $financialYear): string
    {
        [, $endYear] = array_map('intval', explode('-', $financialYear));

        return sprintf('%d-%d', $endYear, $endYear + 1);
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

    private function workflowEvent(string $status, User $actor, string $note): array
    {
        return ['status' => $status, 'actor_user_id' => $actor->id, 'actor' => $actor->name, 'note' => $note, 'at' => now()->toISOString()];
    }

    private function nextDocumentNumber(): string
    {
        return sprintf('TAX-%05d', EmployeeTaxDocument::query()->withTrashed()->count() + 10001);
    }
}
