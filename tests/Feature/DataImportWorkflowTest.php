<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\DataImportBatch;
use App\Models\Employee;
use App\Models\ProspectInquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DataImportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_admin_can_preview_and_post_prospect_inquiry_import(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $csv = $this->prospectInquiryCsv([
            ['SKY-PUN', 'Import Prospect One', 'import.one@example.test', '+91 99000 10001', 'Website', 'website', 'phone', '9000000', '11500000', 'Interested in 2BHK.', 'yes'],
            ['GRN-PUN', 'Import Prospect Two', 'import.two@example.test', '+91 99000 10002', 'Referral', 'referral', 'email', '7500000', '9800000', 'Requested brochure.', 'true'],
        ]);

        $preview = $this->actingAs($admin)->postJson(route('settings.data-imports.preview'), [
            'import_type' => DataImportBatch::TYPE_CRM_PROSPECT_INQUIRIES,
            'source_file' => UploadedFile::fake()->createWithContent('prospects.csv', $csv),
            'note' => 'Preview imported inquiries.',
        ]);

        $preview
            ->assertCreated()
            ->assertJsonPath('data.status', DataImportBatch::STATUS_PREVIEW)
            ->assertJsonPath('data.total_rows', 2)
            ->assertJsonPath('data.valid_rows', 2)
            ->assertJsonPath('data.invalid_rows', 0)
            ->assertJsonPath('data.reconciliation_summary.project_counts.SKY-PUN', 1);

        $batch = DataImportBatch::where('import_number', $preview->json('data.import_number'))->firstOrFail();

        $post = $this->actingAs($admin)->postJson(route('settings.data-imports.post', $batch), [
            'note' => 'Post after reconciliation.',
        ]);

        $post
            ->assertOk()
            ->assertJsonPath('data.status', DataImportBatch::STATUS_POSTED)
            ->assertJsonPath('data.reconciliation_summary.posted_rows', 2);

        $this->assertDatabaseHas('prospect_inquiries', [
            'email' => 'import.one@example.test',
            'name' => 'Import Prospect One',
            'status' => ProspectInquiry::STATUS_NEW,
        ]);

        $this->assertDatabaseHas('prospect_inquiries', [
            'email' => 'import.two@example.test',
            'name' => 'Import Prospect Two',
            'status' => ProspectInquiry::STATUS_NEW,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'settings.data_import.previewed',
            'user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'settings.data_import.posted',
            'user_id' => $admin->id,
        ]);

        $this->assertGreaterThanOrEqual(
            2,
            AuditEvent::where('event_type', 'crm.prospect_inquiry.captured')
                ->where('user_id', $admin->id)
                ->count(),
        );
    }

    public function test_settings_admin_data_import_browser_workspace_redirects_to_approved_ui(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $csv = $this->prospectInquiryCsv([
            ['SKY-PUN', 'Blade Import Prospect', 'blade.import@example.test', '+91 99000 20001', 'Website', 'website', 'email', '8000000', '10000000', 'Blade import test.', 'yes'],
        ]);

        $this->actingAs($admin)
            ->get(route('settings.data-imports.index'))
            ->assertOk()
            ->assertSee('Data Import Center')
            ->assertSee('class="b360-shell"', false)
            ->assertDontSee('id="root"', false);

        $this->actingAs($admin)
            ->post(route('settings.data-imports.preview'), [
                'company_id' => $company->id,
                'import_type' => DataImportBatch::TYPE_CRM_PROSPECT_INQUIRIES,
                'source_file' => UploadedFile::fake()->createWithContent('blade-prospects.csv', $csv),
                'note' => 'Preview through native Blade import center.',
            ])
            ->assertRedirect(route('settings.data-imports.index'))
            ->assertSessionHas('status');

        $batch = DataImportBatch::where('source_filename', 'blade-prospects.csv')->firstOrFail();

        $this->assertSame(DataImportBatch::STATUS_PREVIEW, $batch->status);
        $this->assertSame(1, $batch->valid_rows);
        $this->assertSame(0, $batch->invalid_rows);

        $this->actingAs($admin)
            ->post(route('settings.data-imports.post', $batch), [
                'note' => 'Post through native Blade import center.',
            ])
            ->assertRedirect(route('settings.data-imports.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('data_import_batches', [
            'id' => $batch->id,
            'status' => DataImportBatch::STATUS_POSTED,
            'posted_by_user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('prospect_inquiries', [
            'email' => 'blade.import@example.test',
            'name' => 'Blade Import Prospect',
        ]);
    }

    public function test_import_preview_reports_row_errors_and_blocks_posting(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $csv = $this->prospectInquiryCsv([
            ['UNKNOWN', '', 'not-an-email', '', 'Website', 'bad_channel', 'sms', '12000000', '9000000', 'Bad row.', 'no'],
        ]);

        $preview = $this->actingAs($admin)->postJson(route('settings.data-imports.preview'), [
            'import_type' => DataImportBatch::TYPE_CRM_PROSPECT_INQUIRIES,
            'source_file' => UploadedFile::fake()->createWithContent('prospects-invalid.csv', $csv),
        ]);

        $preview
            ->assertCreated()
            ->assertJsonPath('data.total_rows', 1)
            ->assertJsonPath('data.valid_rows', 0)
            ->assertJsonPath('data.invalid_rows', 1)
            ->assertJsonPath('data.error_report.0.row_number', 2);

        $batch = DataImportBatch::where('import_number', $preview->json('data.import_number'))->firstOrFail();

        $this->actingAs($admin)
            ->postJson(route('settings.data-imports.post', $batch))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['data_import_batch']);

        $this->assertDatabaseMissing('prospect_inquiries', [
            'email' => 'not-an-email',
        ]);
    }

    public function test_settings_admin_can_preview_and_post_hr_employee_import(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $csv = $this->employeeCsv([
            ['EMP-IMPORT-101', 'Imported Employee One', 'Site Engineer', 'Construction', 'B1', 'full_time', 'active', '2026-06-01', 'MH', '', '', '', '65000', 'ABCDE1234F', '123412341234', '100200300400', '123456789012'],
            ['EMP-IMPORT-102', 'Imported Employee Two', 'Sales Executive', 'Sales', 'C', 'full_time', 'active', '2026-06-15', 'MH', '', '', '', '52000', '', '', '', ''],
        ]);

        $preview = $this->actingAs($admin)->postJson(route('settings.data-imports.preview'), [
            'import_type' => DataImportBatch::TYPE_HR_EMPLOYEES,
            'source_file' => UploadedFile::fake()->createWithContent('employees.csv', $csv),
            'note' => 'Preview imported employees.',
        ]);

        $preview
            ->assertCreated()
            ->assertJsonPath('data.import_type', DataImportBatch::TYPE_HR_EMPLOYEES)
            ->assertJsonPath('data.status', DataImportBatch::STATUS_PREVIEW)
            ->assertJsonPath('data.total_rows', 2)
            ->assertJsonPath('data.valid_rows', 2)
            ->assertJsonPath('data.invalid_rows', 0)
            ->assertJsonPath('data.reconciliation_summary.department_counts.Construction', 1);

        $batch = DataImportBatch::where('import_number', $preview->json('data.import_number'))->firstOrFail();

        $post = $this->actingAs($admin)->postJson(route('settings.data-imports.post', $batch), [
            'note' => 'Post employee import after HR reconciliation.',
        ]);

        $post
            ->assertOk()
            ->assertJsonPath('data.status', DataImportBatch::STATUS_POSTED)
            ->assertJsonPath('data.reconciliation_summary.posted_rows', 2)
            ->assertJsonPath('data.reconciliation_summary.created_employees.0', 'EMP-IMPORT-101');

        $this->assertDatabaseHas('employees', [
            'employee_code' => 'EMP-IMPORT-101',
            'name' => 'Imported Employee One',
            'department' => 'Construction',
            'employment_type' => 'full_time',
            'status' => 'active',
        ]);

        $employee = Employee::where('employee_code', 'EMP-IMPORT-101')->firstOrFail();
        $this->assertSame('ABCDE1234F', $employee->sensitive_profile['pan']);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'settings.data_import.previewed',
            'user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'settings.data_import.posted',
            'user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'hr.employee.created',
            'user_id' => $admin->id,
        ]);
    }

    public function test_hr_employee_import_reports_duplicate_and_invalid_rows(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $existing = Employee::query()->firstOrFail();
        $csv = $this->employeeCsv([
            [$existing->employee_code, '', 'Bad Role', '', 'B1', 'bad_type', 'unknown', '2027-01-01', 'MAHARASHTRA-LONG', 'BAD-BRANCH', 'BAD-PROJECT', 'BAD-MANAGER', '-1', '', '', '', ''],
            ['EMP-DUP-IMPORT', 'Duplicate One', 'Engineer', 'Construction', 'B1', 'full_time', 'active', '2026-06-01', 'MH', '', '', '', '50000', '', '', '', ''],
            ['EMP-DUP-IMPORT', 'Duplicate Two', 'Engineer', 'Construction', 'B1', 'full_time', 'active', '2026-06-01', 'MH', '', '', '', '50000', '', '', '', ''],
        ]);

        $preview = $this->actingAs($admin)->postJson(route('settings.data-imports.preview'), [
            'import_type' => DataImportBatch::TYPE_HR_EMPLOYEES,
            'source_file' => UploadedFile::fake()->createWithContent('employees-invalid.csv', $csv),
        ]);

        $preview
            ->assertCreated()
            ->assertJsonPath('data.total_rows', 3)
            ->assertJsonPath('data.valid_rows', 1)
            ->assertJsonPath('data.invalid_rows', 2)
            ->assertJsonPath('data.error_report.0.row_number', 2);

        $batch = DataImportBatch::where('import_number', $preview->json('data.import_number'))->firstOrFail();

        $this->actingAs($admin)
            ->postJson(route('settings.data-imports.post', $batch))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['data_import_batch']);

        $this->assertDatabaseMissing('employees', [
            'employee_code' => 'EMP-DUP-IMPORT',
            'name' => 'Duplicate One',
        ]);
    }

    public function test_import_rejects_posted_duplicate_file_checksum(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $csv = $this->prospectInquiryCsv([
            ['SKY-PUN', 'Checksum Prospect', 'checksum.prospect@example.test', '', 'Website', 'website', 'email', '', '', 'Checksum test.', '1'],
        ]);

        $firstPreview = $this->actingAs($admin)->postJson(route('settings.data-imports.preview'), [
            'import_type' => DataImportBatch::TYPE_CRM_PROSPECT_INQUIRIES,
            'source_file' => UploadedFile::fake()->createWithContent('checksum.csv', $csv),
        ])->assertCreated();

        $batch = DataImportBatch::where('import_number', $firstPreview->json('data.import_number'))->firstOrFail();

        $this->actingAs($admin)
            ->postJson(route('settings.data-imports.post', $batch))
            ->assertOk();

        $this->actingAs($admin)->postJson(route('settings.data-imports.preview'), [
            'import_type' => DataImportBatch::TYPE_CRM_PROSPECT_INQUIRIES,
            'source_file' => UploadedFile::fake()->createWithContent('checksum-again.csv', $csv),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_file']);
    }

    public function test_global_user_import_is_bound_to_active_company_and_index_is_scoped(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $csv = $this->prospectInquiryCsv([
            ['SKY-PUN', 'Scoped Prospect', 'scoped.prospect@example.test', '', 'Website', 'website', 'email', '', '', 'Scoped import.', 'yes'],
        ]);

        $this->actingAs($director)->postJson(route('settings.data-imports.preview'), [
            'import_type' => DataImportBatch::TYPE_CRM_PROSPECT_INQUIRIES,
            'source_file' => UploadedFile::fake()->createWithContent('global-missing-company.csv', $csv),
        ])
            ->assertCreated();

        $this->assertDatabaseHas('data_import_batches', [
            'company_id' => $company->id,
            'source_filename' => 'global-missing-company.csv',
        ]);

        $this->actingAs($admin)
            ->getJson(route('settings.data-imports.index', ['company_id' => $otherCompany->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id']);
    }

    public function test_partner_cannot_access_data_import_workflow(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $batch = DataImportBatch::create([
            'company_id' => $company->id,
            'created_by_user_id' => $admin->id,
            'import_number' => 'IMP-PARTNER-DENY',
            'import_type' => DataImportBatch::TYPE_CRM_PROSPECT_INQUIRIES,
            'source_filename' => 'partner-deny.csv',
            'checksum' => hash('sha256', 'partner-deny'),
            'status' => DataImportBatch::STATUS_PREVIEW,
            'total_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
        ]);
        $csv = $this->prospectInquiryCsv([
            ['SKY-PUN', 'Partner Denied', 'partner.denied@example.test', '', 'Website', 'website', 'email', '', '', 'Denied.', 'yes'],
        ]);

        $this->actingAs($partner)
            ->getJson(route('settings.data-imports.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('settings.data-imports.preview'), [
                'import_type' => DataImportBatch::TYPE_CRM_PROSPECT_INQUIRIES,
                'source_file' => UploadedFile::fake()->createWithContent('partner-denied.csv', $csv),
            ])
            ->assertForbidden();

        $this->actingAs($partner)
            ->postJson(route('settings.data-imports.post', $batch))
            ->assertForbidden();
    }

    /**
     * @param array<int, array<int, string>> $rows
     */
    private function prospectInquiryCsv(array $rows): string
    {
        $lines = [
            'project_code,name,email,phone,source,channel,preferred_contact_method,budget_min,budget_max,message,consent_to_contact',
        ];

        foreach ($rows as $row) {
            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, $row);
            rewind($handle);
            $line = stream_get_contents($handle);
            fclose($handle);

            $lines[] = trim((string) $line);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param array<int, array<int, string>> $rows
     */
    private function employeeCsv(array $rows): string
    {
        $lines = [
            'employee_code,name,designation,department,grade,employment_type,status,joined_on,statutory_state,branch_code,project_code,manager_employee_code,monthly_ctc,pan,aadhaar,uan,bank_account',
        ];

        foreach ($rows as $row) {
            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, $row);
            rewind($handle);
            $line = stream_get_contents($handle);
            fclose($handle);

            $lines[] = trim((string) $line);
        }

        return implode("\n", $lines)."\n";
    }
}
