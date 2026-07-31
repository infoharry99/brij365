<?php

namespace App\Services\Settings;

use App\Models\Company;
use App\Models\DataImportBatch;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProspectInquiry;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Crm\ProspectInquiryService;
use App\Services\Hr\EmployeeProfileService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DataImportService
{
    private const PROSPECT_INQUIRY_HEADERS = [
        'project_code',
        'name',
        'email',
        'phone',
        'source',
        'channel',
        'preferred_contact_method',
        'budget_min',
        'budget_max',
        'message',
        'consent_to_contact',
    ];

    private const HR_EMPLOYEE_HEADERS = [
        'employee_code',
        'name',
        'designation',
        'department',
        'grade',
        'employment_type',
        'status',
        'joined_on',
        'statutory_state',
        'branch_code',
        'project_code',
        'manager_employee_code',
        'monthly_ctc',
        'pan',
        'aadhaar',
        'uan',
        'bank_account',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ProspectInquiryService $prospectInquiryService,
        private readonly EmployeeProfileService $employeeProfileService,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function preview(array $data, UploadedFile $file, User $actor, ?Request $request = null): DataImportBatch
    {
        $companyId = $this->companyIdForImport($data, $actor);
        $rawContent = $this->readUploadedFile($file);
        $checksum = hash('sha256', $rawContent);

        $existingPosted = DataImportBatch::query()
            ->where('company_id', $companyId)
            ->where('import_type', $data['import_type'])
            ->where('checksum', $checksum)
            ->where('status', DataImportBatch::STATUS_POSTED)
            ->exists();

        if ($existingPosted) {
            throw ValidationException::withMessages([
                'source_file' => 'This file checksum has already been posted for the selected company and import type.',
            ]);
        }

        $parsed = $this->parseCsvForImportType($data['import_type'], $rawContent, $companyId);

        return DB::transaction(function () use ($data, $file, $actor, $request, $companyId, $checksum, $parsed): DataImportBatch {
            $batch = DataImportBatch::create([
                'company_id' => $companyId,
                'created_by_user_id' => $actor->id,
                'import_number' => $this->nextImportNumber(),
                'import_type' => $data['import_type'],
                'source_filename' => substr($file->getClientOriginalName(), 0, 255),
                'checksum' => $checksum,
                'status' => DataImportBatch::STATUS_PREVIEW,
                'total_rows' => $parsed['summary']['total_rows'],
                'valid_rows' => $parsed['summary']['valid_rows'],
                'invalid_rows' => $parsed['summary']['invalid_rows'],
                'source_rows' => $parsed['source_rows'],
                'preview_rows' => $parsed['preview_rows'],
                'error_report' => $parsed['error_report'],
                'reconciliation_summary' => $parsed['summary'],
                'workflow_history' => [
                    $this->workflowEvent('preview', $actor, $data['note'] ?? 'Import preview generated.'),
                ],
                'metadata' => [
                    'supported_headers' => $this->headersForImportType($data['import_type']),
                    'source' => 'settings.data_imports',
                ],
            ]);

            $this->auditLogger->record(
                $actor,
                'settings.data_import.previewed',
                'Previewed data import batch',
                $batch,
                [
                    'import_number' => $batch->import_number,
                    'import_type' => $batch->import_type,
                    'total_rows' => $batch->total_rows,
                    'valid_rows' => $batch->valid_rows,
                    'invalid_rows' => $batch->invalid_rows,
                ],
                $request,
            );

            return $batch->load(['company', 'createdBy', 'postedBy']);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function post(DataImportBatch $batch, array $data, User $actor, ?Request $request = null): DataImportBatch
    {
        if ($batch->status !== DataImportBatch::STATUS_PREVIEW) {
            throw ValidationException::withMessages([
                'data_import_batch' => 'Only preview import batches can be posted.',
            ]);
        }

        if ($batch->invalid_rows > 0) {
            throw ValidationException::withMessages([
                'data_import_batch' => 'Import batches with validation errors cannot be posted.',
            ]);
        }

        $duplicatePosted = DataImportBatch::query()
            ->whereKeyNot($batch->id)
            ->where('company_id', $batch->company_id)
            ->where('import_type', $batch->import_type)
            ->where('checksum', $batch->checksum)
            ->where('status', DataImportBatch::STATUS_POSTED)
            ->exists();

        if ($duplicatePosted) {
            throw ValidationException::withMessages([
                'data_import_batch' => 'A posted import with the same checksum already exists.',
            ]);
        }

        return DB::transaction(function () use ($batch, $data, $actor, $request): DataImportBatch {
            if ($batch->import_type === DataImportBatch::TYPE_HR_EMPLOYEES) {
                return $this->postHrEmployees($batch, $data, $actor, $request);
            }

            $createdInquiryNumbers = [];

            foreach ($batch->source_rows ?? [] as $row) {
                $inquiry = $this->prospectInquiryService->capturePublic(
                    [
                        'project_id' => $row['project_id'],
                        'name' => $row['name'],
                        'email' => $row['email'] ?: null,
                        'phone' => $row['phone'] ?: null,
                        'source' => $row['source'] ?: 'Bulk Import',
                        'channel' => $row['channel'] ?: 'other',
                        'preferred_contact_method' => $row['preferred_contact_method'] ?: null,
                        'budget_min' => $row['budget_min'] ?: null,
                        'budget_max' => $row['budget_max'] ?: null,
                        'message' => $row['message'] ?: null,
                        'consent_to_contact' => true,
                    ],
                    $request,
                    $actor,
                    [
                        'import_number' => $batch->import_number,
                        'row_number' => $row['_row_number'],
                    ],
                );

                $createdInquiryNumbers[] = $inquiry->inquiry_number;
            }

            $summary = array_merge($batch->reconciliation_summary ?? [], [
                'posted_rows' => count($createdInquiryNumbers),
                'created_inquiries' => $createdInquiryNumbers,
                'posted_at' => now()->toISOString(),
            ]);

            $batch->forceFill([
                'status' => DataImportBatch::STATUS_POSTED,
                'posted_by_user_id' => $actor->id,
                'posted_at' => now(),
                'reconciliation_summary' => $summary,
                'workflow_history' => array_merge($batch->workflow_history ?? [], [
                    $this->workflowEvent('posted', $actor, $data['note'] ?? 'Import posted to business records.'),
                ]),
            ])->save();

            $this->auditLogger->record(
                $actor,
                'settings.data_import.posted',
                'Posted data import batch',
                $batch,
                [
                    'import_number' => $batch->import_number,
                    'import_type' => $batch->import_type,
                    'posted_rows' => count($createdInquiryNumbers),
                    'created_inquiries' => $createdInquiryNumbers,
                ],
                $request,
            );

            return $batch->refresh()->load(['company', 'createdBy', 'postedBy']);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    private function postHrEmployees(DataImportBatch $batch, array $data, User $actor, ?Request $request = null): DataImportBatch
    {
        $createdEmployeeCodes = [];

        foreach ($batch->source_rows ?? [] as $row) {
            $employee = $this->employeeProfileService->create([
                'company_id' => $batch->company_id,
                'branch_id' => $row['branch_id'] ?? null,
                'project_id' => $row['project_id'] ?? null,
                'manager_employee_id' => $row['manager_employee_id'] ?? null,
                'employee_code' => $row['employee_code'],
                'name' => $row['name'],
                'designation' => $row['designation'],
                'department' => $row['department'],
                'grade' => $row['grade'] ?: null,
                'employment_type' => $row['employment_type'],
                'status' => $row['status'] ?: 'active',
                'joined_on' => $row['joined_on'] ?: null,
                'statutory_state' => $row['statutory_state'] ?: null,
                'monthly_ctc' => $row['monthly_ctc'] ?: null,
                'sensitive_profile' => array_filter([
                    'pan' => $row['pan'] ?: null,
                    'aadhaar' => $row['aadhaar'] ?: null,
                    'uan' => $row['uan'] ?: null,
                    'bank_account' => $row['bank_account'] ?: null,
                ], fn ($value): bool => $value !== null && $value !== ''),
            ], $actor, $request);

            $createdEmployeeCodes[] = $employee->employee_code;
        }

        $summary = array_merge($batch->reconciliation_summary ?? [], [
            'posted_rows' => count($createdEmployeeCodes),
            'created_employees' => $createdEmployeeCodes,
            'posted_at' => now()->toISOString(),
        ]);

        $batch->forceFill([
            'status' => DataImportBatch::STATUS_POSTED,
            'posted_by_user_id' => $actor->id,
            'posted_at' => now(),
            'reconciliation_summary' => $summary,
            'workflow_history' => array_merge($batch->workflow_history ?? [], [
                $this->workflowEvent('posted', $actor, $data['note'] ?? 'Employee import posted to HR master records.'),
            ]),
        ])->save();

        $this->auditLogger->record(
            $actor,
            'settings.data_import.posted',
            'Posted data import batch',
            $batch,
            [
                'import_number' => $batch->import_number,
                'import_type' => $batch->import_type,
                'posted_rows' => count($createdEmployeeCodes),
                'created_employees' => $createdEmployeeCodes,
            ],
            $request,
        );

        return $batch->refresh()->load(['company', 'createdBy', 'postedBy']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function companyIdForImport(array $data, User $actor): int
    {
        if ($actor->hasPermission('*')) {
            return (int) $data['company_id'];
        }

        return (int) $actor->company_id;
    }

    private function readUploadedFile(UploadedFile $file): string
    {
        $path = $file->getRealPath();

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages(['source_file' => 'Unable to read uploaded import file.']);
        }

        $contents = file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            throw ValidationException::withMessages(['source_file' => 'The import file is empty or unreadable.']);
        }

        return $contents;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseCsvForImportType(string $importType, string $rawContent, int $companyId): array
    {
        return match ($importType) {
            DataImportBatch::TYPE_CRM_PROSPECT_INQUIRIES => $this->parseProspectInquiryCsv($rawContent, $companyId),
            DataImportBatch::TYPE_HR_EMPLOYEES => $this->parseHrEmployeeCsv($rawContent, $companyId),
            default => throw ValidationException::withMessages([
                'import_type' => 'Unsupported import type.',
            ]),
        };
    }

    /**
     * @return array<int, string>
     */
    private function headersForImportType(string $importType): array
    {
        return match ($importType) {
            DataImportBatch::TYPE_CRM_PROSPECT_INQUIRIES => self::PROSPECT_INQUIRY_HEADERS,
            DataImportBatch::TYPE_HR_EMPLOYEES => self::HR_EMPLOYEE_HEADERS,
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function parseProspectInquiryCsv(string $rawContent, int $companyId): array
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Unable to allocate import parser buffer.');
        }

        fwrite($handle, $rawContent);
        rewind($handle);

        $headers = fgetcsv($handle);

        if (! is_array($headers)) {
            fclose($handle);

            throw ValidationException::withMessages(['source_file' => 'The import file must include a CSV header row.']);
        }

        $headers = array_map(fn ($header): string => $this->normalizeHeader((string) $header), $headers);

        if ($headers !== self::PROSPECT_INQUIRY_HEADERS) {
            fclose($handle);

            throw ValidationException::withMessages([
                'source_file' => 'The prospect inquiry import header must be: '.implode(',', self::PROSPECT_INQUIRY_HEADERS),
            ]);
        }

        $sourceRows = [];
        $previewRows = [];
        $errorReport = [];
        $projectCounts = [];
        $fileContactKeys = [];
        $duplicateContactRows = 0;
        $rowNumber = 1;

        while (($line = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($this->isEmptyCsvLine($line)) {
                continue;
            }

            $row = array_combine($headers, array_pad($line, count($headers), ''));

            if (! is_array($row)) {
                continue;
            }

            $row = array_map(fn ($value): string => trim((string) $value), $row);
            $validation = $this->validateProspectInquiryImportRow($row, $companyId, $fileContactKeys);
            $warnings = $validation['warnings'];

            if ($warnings !== []) {
                $duplicateContactRows++;
            }

            if ($validation['errors'] !== []) {
                foreach ($validation['errors'] as $field => $message) {
                    $errorReport[] = [
                        'row_number' => $rowNumber,
                        'field' => $field,
                        'message' => $message,
                        'value' => $row[$field] ?? null,
                    ];
                }
            } else {
                $projectCounts[$row['project_code']] = ($projectCounts[$row['project_code']] ?? 0) + 1;
                $sourceRows[] = array_merge($row, [
                    '_row_number' => $rowNumber,
                    'project_id' => $validation['project_id'],
                    'email' => strtolower($row['email']),
                ]);
            }

            $previewRows[] = [
                'row_number' => $rowNumber,
                'project_code' => $row['project_code'],
                'name' => $row['name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'status' => $validation['errors'] === [] ? 'valid' : 'invalid',
                'warnings' => $warnings,
                'errors' => $validation['errors'],
            ];
        }

        fclose($handle);

        $totalRows = count($previewRows);
        $invalidRows = count(array_filter($previewRows, fn (array $row): bool => $row['status'] === 'invalid'));

        return [
            'source_rows' => $sourceRows,
            'preview_rows' => $previewRows,
            'error_report' => $errorReport,
            'summary' => [
                'total_rows' => $totalRows,
                'valid_rows' => $totalRows - $invalidRows,
                'invalid_rows' => $invalidRows,
                'duplicate_contact_rows' => $duplicateContactRows,
                'project_counts' => $projectCounts,
            ],
        ];
    }

    /**
     * @param array<string, string> $row
     * @param array<string, bool> $fileContactKeys
     * @return array{project_id: int|null, errors: array<string, string>, warnings: array<int, string>}
     */
    private function validateProspectInquiryImportRow(array $row, int $companyId, array &$fileContactKeys): array
    {
        $errors = [];
        $warnings = [];
        $projectId = null;

        $project = Project::query()
            ->where('code', $row['project_code'])
            ->where('company_id', $companyId)
            ->first();

        if (! $project) {
            $errors['project_code'] = 'Project code must exist for the selected company.';
        } elseif ($project->status !== 'active') {
            $errors['project_code'] = 'Project must be active for prospect inquiry imports.';
        } else {
            $projectId = (int) $project->id;
        }

        if ($row['name'] === '') {
            $errors['name'] = 'Name is required.';
        }

        if ($row['email'] === '' && $row['phone'] === '') {
            $errors['email'] = 'Either email or phone is required.';
            $errors['phone'] = 'Either phone or email is required.';
        }

        if ($row['email'] !== '' && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email must be a valid email address.';
        }

        if ($row['channel'] !== '' && ! in_array($row['channel'], [
            'website',
            'mobile_app',
            'buyer_portal',
            'landing_page',
            'channel_partner',
            'referral',
            'social',
            'whatsapp',
            'phone',
            'other',
        ], true)) {
            $errors['channel'] = 'Channel is not supported for prospect inquiry imports.';
        }

        if ($row['preferred_contact_method'] !== '' && ! in_array($row['preferred_contact_method'], ['phone', 'email', 'whatsapp'], true)) {
            $errors['preferred_contact_method'] = 'Preferred contact method must be phone, email or whatsapp.';
        }

        foreach (['budget_min', 'budget_max'] as $field) {
            if ($row[$field] !== '' && (! is_numeric($row[$field]) || (float) $row[$field] < 0)) {
                $errors[$field] = 'Budget values must be non-negative numbers.';
            }
        }

        if ($row['budget_min'] !== '' && $row['budget_max'] !== '' && is_numeric($row['budget_min']) && is_numeric($row['budget_max'])
            && (float) $row['budget_max'] < (float) $row['budget_min']) {
            $errors['budget_max'] = 'Maximum budget must be greater than or equal to minimum budget.';
        }

        if (! in_array(strtolower($row['consent_to_contact']), ['yes', 'true', '1'], true)) {
            $errors['consent_to_contact'] = 'Consent to contact must be yes, true or 1.';
        }

        if ($projectId !== null && ($row['email'] !== '' || $row['phone'] !== '')) {
            $contactKey = $projectId.'|'.strtolower($row['email']).'|'.$row['phone'];

            if (isset($fileContactKeys[$contactKey])) {
                $warnings[] = 'Duplicate contact found within this import file.';
            }

            $fileContactKeys[$contactKey] = true;

            $existingDuplicate = ProspectInquiry::query()
                ->where('project_id', $projectId)
                ->whereIn('status', ProspectInquiry::OPEN_STATUSES)
                ->where(function ($query) use ($row): void {
                    if ($row['email'] !== '') {
                        $query->orWhere('email', strtolower($row['email']));
                    }

                    if ($row['phone'] !== '') {
                        $query->orWhere('phone', $row['phone']);
                    }
                })
                ->exists();

            if ($existingDuplicate) {
                $warnings[] = 'Existing open prospect inquiry found for this project/contact.';
            }
        }

        return [
            'project_id' => $projectId,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseHrEmployeeCsv(string $rawContent, int $companyId): array
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Unable to allocate import parser buffer.');
        }

        fwrite($handle, $rawContent);
        rewind($handle);

        $headers = fgetcsv($handle);

        if (! is_array($headers)) {
            fclose($handle);

            throw ValidationException::withMessages(['source_file' => 'The import file must include a CSV header row.']);
        }

        $headers = array_map(fn ($header): string => $this->normalizeHeader((string) $header), $headers);

        if ($headers !== self::HR_EMPLOYEE_HEADERS) {
            fclose($handle);

            throw ValidationException::withMessages([
                'source_file' => 'The HR employee import header must be: '.implode(',', self::HR_EMPLOYEE_HEADERS),
            ]);
        }

        $sourceRows = [];
        $previewRows = [];
        $errorReport = [];
        $departmentCounts = [];
        $fileEmployeeCodes = [];
        $duplicateEmployeeRows = 0;
        $rowNumber = 1;

        while (($line = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($this->isEmptyCsvLine($line)) {
                continue;
            }

            $row = array_combine($headers, array_pad($line, count($headers), ''));

            if (! is_array($row)) {
                continue;
            }

            $row = array_map(fn ($value): string => trim((string) $value), $row);
            $validation = $this->validateHrEmployeeImportRow($row, $companyId, $fileEmployeeCodes);
            $warnings = $validation['warnings'];

            if ($warnings !== []) {
                $duplicateEmployeeRows++;
            }

            if ($validation['errors'] !== []) {
                foreach ($validation['errors'] as $field => $message) {
                    $errorReport[] = [
                        'row_number' => $rowNumber,
                        'field' => $field,
                        'message' => $message,
                        'value' => $row[$field] ?? null,
                    ];
                }
            } else {
                $departmentCounts[$row['department']] = ($departmentCounts[$row['department']] ?? 0) + 1;
                $sourceRows[] = array_merge($row, [
                    '_row_number' => $rowNumber,
                    'employee_code' => strtoupper($row['employee_code']),
                    'employment_type' => strtolower($row['employment_type']),
                    'status' => strtolower($row['status'] ?: 'active'),
                    'branch_id' => $validation['branch_id'],
                    'project_id' => $validation['project_id'],
                    'manager_employee_id' => $validation['manager_employee_id'],
                ]);
            }

            $previewRows[] = [
                'row_number' => $rowNumber,
                'employee_code' => strtoupper($row['employee_code']),
                'name' => $row['name'],
                'department' => $row['department'],
                'designation' => $row['designation'],
                'status' => $validation['errors'] === [] ? 'valid' : 'invalid',
                'warnings' => $warnings,
                'errors' => $validation['errors'],
            ];
        }

        fclose($handle);

        $totalRows = count($previewRows);
        $invalidRows = count(array_filter($previewRows, fn (array $row): bool => $row['status'] === 'invalid'));

        return [
            'source_rows' => $sourceRows,
            'preview_rows' => $previewRows,
            'error_report' => $errorReport,
            'summary' => [
                'total_rows' => $totalRows,
                'valid_rows' => $totalRows - $invalidRows,
                'invalid_rows' => $invalidRows,
                'duplicate_employee_rows' => $duplicateEmployeeRows,
                'department_counts' => $departmentCounts,
            ],
        ];
    }

    /**
     * @param array<string, string> $row
     * @param array<string, bool> $fileEmployeeCodes
     * @return array{branch_id: int|null, project_id: int|null, manager_employee_id: int|null, errors: array<string, string>, warnings: array<int, string>}
     */
    private function validateHrEmployeeImportRow(array $row, int $companyId, array &$fileEmployeeCodes): array
    {
        $errors = [];
        $warnings = [];
        $branchId = null;
        $projectId = null;
        $managerEmployeeId = null;
        $employeeCode = strtoupper($row['employee_code']);

        if ($employeeCode === '') {
            $errors['employee_code'] = 'Employee code is required.';
        } elseif (! preg_match('/^[A-Z0-9-]+$/', $employeeCode)) {
            $errors['employee_code'] = 'Employee code may contain only uppercase letters, numbers and hyphen.';
        } else {
            if (isset($fileEmployeeCodes[$employeeCode])) {
                $errors['employee_code'] = 'Duplicate employee code found within this import file.';
            }

            $fileEmployeeCodes[$employeeCode] = true;

            if (Employee::query()->where('employee_code', $employeeCode)->exists()) {
                $errors['employee_code'] = 'Employee code already exists.';
            }
        }

        foreach (['name', 'designation', 'department', 'employment_type'] as $field) {
            if ($row[$field] === '') {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)).' is required.';
            }
        }

        if ($row['employment_type'] !== '' && ! in_array(strtolower($row['employment_type']), ['full_time', 'part_time', 'contract', 'intern', 'consultant'], true)) {
            $errors['employment_type'] = 'Employment type must be full_time, part_time, contract, intern or consultant.';
        }

        if ($row['status'] !== '' && ! in_array(strtolower($row['status']), ['active', 'inactive', 'on_notice', 'separated'], true)) {
            $errors['status'] = 'Status must be active, inactive, on_notice or separated.';
        }

        if ($row['joined_on'] !== '') {
            $date = date_create($row['joined_on']);

            if (! $date || $date->format('Y-m-d') !== $row['joined_on']) {
                $errors['joined_on'] = 'Joined on must use YYYY-MM-DD format.';
            } elseif ($row['joined_on'] > now()->toDateString()) {
                $errors['joined_on'] = 'Joined on cannot be a future date.';
            }
        }

        if ($row['monthly_ctc'] !== '' && (! is_numeric($row['monthly_ctc']) || (float) $row['monthly_ctc'] < 0)) {
            $errors['monthly_ctc'] = 'Monthly CTC must be a non-negative number.';
        }

        if ($row['statutory_state'] !== '' && strlen($row['statutory_state']) > 8) {
            $errors['statutory_state'] = 'Statutory state code may not exceed 8 characters.';
        }

        if ($row['branch_code'] !== '') {
            $branch = Branch::query()
                ->where('company_id', $companyId)
                ->where('code', $row['branch_code'])
                ->where('status', 'active')
                ->first();

            if (! $branch) {
                $errors['branch_code'] = 'Branch code must exist and be active for the selected company.';
            } else {
                $branchId = (int) $branch->id;
            }
        }

        if ($row['project_code'] !== '') {
            $project = Project::query()
                ->where('company_id', $companyId)
                ->where('code', $row['project_code'])
                ->where('status', 'active')
                ->first();

            if (! $project) {
                $errors['project_code'] = 'Project code must exist and be active for the selected company.';
            } else {
                $projectId = (int) $project->id;
            }
        }

        if ($row['manager_employee_code'] !== '') {
            $manager = Employee::query()
                ->where('company_id', $companyId)
                ->where('employee_code', strtoupper($row['manager_employee_code']))
                ->where('status', 'active')
                ->first();

            if (! $manager) {
                $errors['manager_employee_code'] = 'Manager employee code must exist and be active in the selected company.';
            } else {
                $managerEmployeeId = (int) $manager->id;
            }
        }

        return [
            'branch_id' => $branchId,
            'project_id' => $projectId,
            'manager_employee_id' => $managerEmployeeId,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<int, string|null> $line
     */
    private function isEmptyCsvLine(array $line): bool
    {
        return trim(implode('', array_map(fn ($value): string => (string) $value, $line))) === '';
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;

        return strtolower(trim($header));
    }

    private function nextImportNumber(): string
    {
        return sprintf('IMP-%05d', DataImportBatch::query()->withTrashed()->count() + 10001);
    }

    /**
     * @return array<string, mixed>
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
}
