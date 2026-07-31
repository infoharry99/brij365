<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\DocumentCategory;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ManagedDocument;
use App\Models\Project;
use App\Models\User;
use App\Services\Documents\DocumentFilePolicy;
use App\Services\Documents\DocumentStoragePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_list_document_categories_and_expiring_documents(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('documents.categories.index', ['owner_type' => 'project']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'RERA_CERT');

        $this->actingAs($sales)
            ->getJson(route('documents.index', ['expires_within_days' => 30]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.document_number', 'DOC-1001')
            ->assertJsonPath('data.0.storage_disk', 'local')
            ->assertJsonPath('data.0.storage_path', 'documents/demo/skyline-rera-certificate.pdf')
            ->assertJsonPath('data.0.download_url', fn (?string $url): bool => is_string($url) && str_contains($url, '/documents/'))
            ->assertJsonPath('data.0.is_expiring_within_30_days', true);
    }

    public function test_authorized_user_can_use_native_blade_document_category_register(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->get(route('documents.categories.index'))
            ->assertOk()
            ->assertSee('Document Categories')
            ->assertSee('Available categories')
            ->assertSee('RERA_CERT')
            ->assertSee('Expiry control')
            ->assertDontSee('id="root"', false);

        $this->actingAs($sales)
            ->get(route('documents.categories.index', ['owner_type' => 'project']))
            ->assertOk()
            ->assertSee('RERA Certificate')
            ->assertSee('value="project" selected', false);
    }

    public function test_authorized_users_can_use_native_blade_document_repository(): void
    {
        $this->seed();

        Storage::fake('local');

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $category = DocumentCategory::where('code', 'RERA_CERT')->firstOrFail();
        $file = UploadedFile::fake()->create('blade-rera-certificate.pdf', 32, 'application/pdf');

        $this->actingAs($sales)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertSee('Secure document repository')
            ->assertDontSee('Native Laravel Blade repository')
            ->assertSee('Submit managed document')
            ->assertSee('Repository filters')
            ->assertSee('Document register')
            ->assertSee('name="document_file"', false)
            ->assertSee('DOC-1001')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);

        $this->actingAs($sales)
            ->post(route('documents.store'), [
                '_return_to' => 'documents.index',
                'document_category_id' => $category->id,
                'title' => 'Blade Uploaded RERA Certificate',
                'owner_type' => 'project',
                'owner_id' => $project->id,
                'storage_disk' => 'local',
                'document_file' => $file,
                'issue_date' => now()->subDay()->toDateString(),
                'expires_on' => now()->addYear()->toDateString(),
                'metadata' => ['source' => 'document_repository_blade'],
            ])
            ->assertRedirect(route('documents.index'))
            ->assertSessionHas('status');

        $document = ManagedDocument::where('title', 'Blade Uploaded RERA Certificate')->firstOrFail();

        $this->assertSame('submitted', $document->status);
        $this->assertStringStartsWith('documents/uploads/', $document->storage_path);
        Storage::disk('local')->assertExists($document->storage_path);

        $this->actingAs($finance)
            ->patch(route('documents.approve', $document), [
                '_return_to' => 'documents.index',
                'approval_note' => 'Approved through the native Blade document repository.',
            ])
            ->assertRedirect(route('documents.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('managed_documents', [
            'id' => $document->id,
            'status' => 'approved',
            'approved_by_user_id' => $finance->id,
        ]);
    }

    public function test_document_register_indexes_validate_filters_and_owner_scope(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $otherCompany = Company::create([
            'code' => 'EXTCO',
            'name' => 'External Builder Co',
            'legal_name' => 'External Builder Co Private Limited',
            'state' => 'MH',
            'status' => 'active',
        ]);
        $otherProject = Project::create([
            'company_id' => $otherCompany->id,
            'code' => 'EXT-PROJ',
            'name' => 'External Project',
            'project_type' => 'residential',
            'city' => 'Mumbai',
            'state' => 'MH',
            'status' => 'active',
            'budget_amount' => 1000000,
            'target_roi_percent' => 10,
        ]);
        $otherCategory = DocumentCategory::create([
            'company_id' => $otherCompany->id,
            'code' => 'EXT_DOC',
            'name' => 'External Document',
            'owner_type' => 'project',
            'expiry_required' => false,
            'reminder_days_before_expiry' => 30,
            'retention_years' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($sales)
            ->getJson(route('documents.categories.index', ['owner_type' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('owner_type');

        $this->actingAs($sales)
            ->getJson(route('documents.categories.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($sales)
            ->getJson(route('documents.categories.index', ['status' => 'approved']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status')
            ->assertJsonPath('errors.status.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($sales)
            ->getJson(route('documents.categories.index', ['per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');

        $this->actingAs($sales)
            ->getJson(route('documents.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($sales)
            ->getJson(route('documents.index', ['project_id' => $project->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_id')
            ->assertJsonPath('errors.project_id.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($sales)
            ->getJson(route('documents.index', ['owner_type' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('owner_type');

        $this->actingAs($sales)
            ->getJson(route('documents.index', ['owner_id' => $project->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('owner_type');

        $this->actingAs($sales)
            ->getJson(route('documents.index', ['status' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($sales)
            ->getJson(route('documents.index', ['expires_within_days' => 5000]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expires_within_days');

        $this->actingAs($sales)
            ->getJson(route('documents.index', ['document_category_id' => $otherCategory->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document_category_id');

        $this->actingAs($sales)
            ->getJson(route('documents.index', [
                'owner_type' => 'project',
                'owner_id' => $otherProject->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('owner_id');

        $this->actingAs($sales)
            ->getJson(route('documents.index', [
                'owner_type' => 'project',
                'owner_id' => $project->id,
                'status' => 'approved',
                'current_only' => true,
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.document_number', 'DOC-1001');
    }

    public function test_document_category_index_uses_configured_large_pagination_limit(): void
    {
        $this->seed();

        Config::set('builder360.pagination.large_max_per_page', 3);

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($sales)
            ->getJson(route('documents.categories.index', ['per_page' => 4]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');

        $this->actingAs($sales)
            ->getJson(route('documents.categories.index', ['per_page' => 3]))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 3);
    }

    public function test_non_global_document_users_without_company_assignment_fail_closed(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $category = DocumentCategory::where('code', 'RERA_CERT')->firstOrFail();

        $submitResponse = $this->actingAs($sales)->postJson(route('documents.store'), [
            'document_category_id' => $category->id,
            'title' => 'Fail Closed Scope RERA Certificate',
            'owner_type' => 'project',
            'owner_id' => $project->id,
            'storage_path' => 'documents/projects/fail-closed-scope-rera.pdf',
            'original_filename' => 'fail-closed-scope-rera.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 320000,
            'checksum_sha256' => hash('sha256', 'fail-closed-scope-rera'),
            'issue_date' => now()->subDay()->toDateString(),
            'expires_on' => now()->addYear()->toDateString(),
        ]);

        $submitResponse->assertCreated();
        $document = ManagedDocument::where('document_number', $submitResponse->json('data.document_number'))->firstOrFail();

        $sales->forceFill(['company_id' => null])->save();
        $finance->forceFill(['company_id' => null])->save();

        $this->actingAs($sales)
            ->getJson(route('documents.categories.index', ['owner_type' => 'project']))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($sales)
            ->getJson(route('documents.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($sales)
            ->getJson(route('documents.index', [
                'owner_type' => 'project',
                'owner_id' => $project->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('owner_id');

        $this->actingAs($sales)
            ->postJson(route('documents.store'), [
                'document_category_id' => $category->id,
                'title' => 'Invalid Missing Company Scope Document',
                'owner_type' => 'project',
                'owner_id' => $project->id,
                'storage_path' => 'documents/projects/invalid-missing-company-scope.pdf',
                'original_filename' => 'invalid-missing-company-scope.pdf',
                'mime_type' => 'application/pdf',
                'file_size_bytes' => 320000,
                'checksum_sha256' => hash('sha256', 'invalid-missing-company-scope'),
                'issue_date' => now()->subDay()->toDateString(),
                'expires_on' => now()->addYear()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('owner_id');

        $this->actingAs($finance)
            ->patchJson(route('documents.approve', $document), [
                'approval_note' => 'Should fail closed.',
            ])
            ->assertForbidden();

        $this->actingAs($sales)
            ->get(route('documents.download', $document))
            ->assertForbidden();
    }

    public function test_document_storage_policy_uses_builder360_config_defaults_and_overrides(): void
    {
        $policy = app(DocumentStoragePolicy::class);

        $this->assertSame(['local', 's3'], config('builder360.documents.allowed_storage_disks'));
        $this->assertSame('documents/', $policy->storagePathPrefix());
        $this->assertSame(['local', 's3'], $policy->allowedDisks());

        config([
            'builder360.documents.allowed_storage_disks' => ['secure_docs'],
            'builder360.documents.storage_path_prefix' => 'vault',
        ]);

        $this->assertSame([], $policy->violations('secure_docs', 'vault/project/rera.pdf', 'rera.pdf'));
        $this->assertArrayHasKey('storage_disk', $policy->violations('local', 'vault/project/rera.pdf', 'rera.pdf'));
        $this->assertArrayHasKey('storage_path', $policy->violations('secure_docs', 'documents/project/rera.pdf', 'rera.pdf'));
    }

    public function test_sales_can_submit_project_document_and_finance_can_approve_it(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $category = DocumentCategory::where('code', 'RERA_CERT')->firstOrFail();

        $submitResponse = $this->actingAs($sales)->postJson(route('documents.store'), [
            'document_category_id' => $category->id,
            'title' => 'Skyline Residency Updated RERA Certificate',
            'owner_type' => 'project',
            'owner_id' => $project->id,
            'storage_path' => 'documents/projects/skyline-rera-updated.pdf',
            'original_filename' => 'skyline-rera-updated.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 320000,
            'checksum_sha256' => hash('sha256', 'skyline-rera-updated'),
            'issue_date' => now()->subDay()->toDateString(),
            'expires_on' => now()->addYear()->toDateString(),
            'metadata' => ['reference' => 'RERA-UPDATED-001'],
        ]);

        $submitResponse
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.owner_type', 'project')
            ->assertJsonPath('data.category.code', 'RERA_CERT');

        $document = ManagedDocument::where('document_number', $submitResponse->json('data.document_number'))->firstOrFail();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'documents.document.submitted',
            'action' => 'Submitted managed document',
            'user_id' => $sales->id,
        ]);

        $this->actingAs($finance)
            ->patchJson(route('documents.approve', $document), [
                'approval_note' => str_repeat('x', 2001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('approval_note');

        $this->actingAs($finance)
            ->patchJson(route('documents.approve', $document), [
                'approval_note' => 'Approved after validating the latest authority certificate.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by.email', 'suresh.iyer@builder360.test');

        $this->assertDatabaseHas('managed_documents', [
            'id' => $document->id,
            'status' => 'approved',
            'approved_by_user_id' => $finance->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'documents.document.approved',
            'action' => 'Approved managed document',
            'user_id' => $finance->id,
        ]);

        $document->refresh();
        $this->assertSame('Approved after validating the latest authority certificate.', $document->metadata['approval_note']);

        $audit = AuditEvent::query()
            ->where('event_type', 'documents.document.approved')
            ->latest()
            ->firstOrFail();
        $this->assertSame('Approved after validating the latest authority certificate.', $audit->metadata['approval_note']);
    }

    public function test_sales_can_upload_project_document_file_and_server_derives_storage_metadata(): void
    {
        $this->seed();

        Storage::fake('local');

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $category = DocumentCategory::where('code', 'RERA_CERT')->firstOrFail();
        $file = UploadedFile::fake()->create('uploaded-rera-certificate.pdf', 24, 'application/pdf');

        $response = $this->actingAs($sales)->post(route('documents.store'), [
            'document_category_id' => $category->id,
            'title' => 'Uploaded RERA Certificate',
            'owner_type' => 'project',
            'owner_id' => $project->id,
            'storage_disk' => 'local',
            'document_file' => $file,
            'issue_date' => now()->subDay()->toDateString(),
            'expires_on' => now()->addYear()->toDateString(),
            'metadata' => [
                'source' => 'document_management_screen',
                'upload_mode' => 'browser_multipart_file',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.original_filename', 'uploaded-rera-certificate.pdf')
            ->assertJsonPath('data.mime_type', 'application/pdf')
            ->assertJsonPath('data.storage_disk', 'local');

        $document = ManagedDocument::where('document_number', $response->json('data.document_number'))->firstOrFail();

        $this->assertStringStartsWith('documents/uploads/', $document->storage_path);
        $this->assertMatchesRegularExpression('/\.pdf$/', $document->storage_path);
        $this->assertSame(64, strlen((string) $document->checksum_sha256));
        $this->assertSame('multipart_file', $document->metadata['upload_mode']);
        Storage::disk('local')->assertExists($document->storage_path);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'documents.document.submitted',
            'action' => 'Submitted managed document',
            'user_id' => $sales->id,
            'auditable_type' => ManagedDocument::class,
            'auditable_id' => $document->id,
        ]);
    }

    public function test_customer_document_company_scope_is_derived_from_customer_business_records(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $customer = Customer::where('code', 'CUS-1001')->firstOrFail();
        $category = DocumentCategory::where('code', 'CUSTOMER_KYC')->firstOrFail();

        $response = $this->actingAs($director)->postJson(route('documents.store'), [
            'document_category_id' => $category->id,
            'title' => 'Rohan Shah Updated KYC',
            'owner_type' => 'customer',
            'owner_id' => $customer->id,
            'storage_path' => 'documents/customers/rohan-shah-updated-kyc.pdf',
            'original_filename' => 'rohan-shah-updated-kyc.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 180000,
            'checksum_sha256' => hash('sha256', 'rohan-shah-updated-kyc'),
            'issue_date' => now()->subDay()->toDateString(),
            'expires_on' => now()->addYear()->toDateString(),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.owner_type', 'customer')
            ->assertJsonPath('data.category.code', 'CUSTOMER_KYC');

        $this->assertDatabaseHas('managed_documents', [
            'document_number' => $response->json('data.document_number'),
            'company_id' => $company->id,
            'owner_type' => 'customer',
            'owner_id' => $customer->id,
            'uploaded_by_user_id' => $director->id,
        ]);
    }

    public function test_customer_document_submission_fails_closed_without_unique_customer_company_context(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $category = DocumentCategory::where('code', 'CUSTOMER_KYC')->firstOrFail();
        $orphanCustomer = Customer::create([
            'code' => 'CUS-ORPHAN',
            'name' => 'Orphan Customer',
            'email' => 'orphan.customer@example.test',
            'phone' => '+91 98111 19999',
            'source' => 'Manual',
            'status' => 'active',
        ]);

        $this->actingAs($sales)->postJson(route('documents.store'), [
            'document_category_id' => $category->id,
            'title' => 'Orphan Customer KYC',
            'owner_type' => 'customer',
            'owner_id' => $orphanCustomer->id,
            'storage_path' => 'documents/customers/orphan-customer-kyc.pdf',
            'original_filename' => 'orphan-customer-kyc.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 180000,
            'checksum_sha256' => hash('sha256', 'orphan-customer-kyc'),
            'issue_date' => now()->subDay()->toDateString(),
            'expires_on' => now()->addYear()->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('owner_id');

        $this->assertDatabaseMissing('managed_documents', [
            'title' => 'Orphan Customer KYC',
        ]);
    }

    public function test_document_submission_requires_expiry_for_expiry_controlled_category(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $category = DocumentCategory::where('code', 'RERA_CERT')->firstOrFail();

        $this->actingAs($sales)->postJson(route('documents.store'), [
            'document_category_id' => $category->id,
            'title' => 'Invalid RERA Certificate Without Expiry',
            'owner_type' => 'project',
            'owner_id' => $project->id,
            'storage_path' => 'documents/projects/invalid-rera.pdf',
            'original_filename' => 'invalid-rera.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 100000,
            'checksum_sha256' => hash('sha256', 'invalid-rera'),
            'issue_date' => now()->subDay()->toDateString(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('expires_on');
    }

    public function test_document_submission_rejects_unsafe_storage_metadata(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $category = DocumentCategory::where('code', 'RERA_CERT')->firstOrFail();

        $basePayload = [
            'document_category_id' => $category->id,
            'title' => 'Unsafe Storage Metadata Document',
            'owner_type' => 'project',
            'owner_id' => $project->id,
            'storage_path' => 'documents/projects/safe.pdf',
            'original_filename' => 'safe.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 320000,
            'checksum_sha256' => hash('sha256', 'unsafe-storage-metadata'),
            'issue_date' => now()->subDay()->toDateString(),
            'expires_on' => now()->addYear()->toDateString(),
        ];

        $cases = [
            [['storage_disk' => 'public'], 'storage_disk'],
            [['storage_path' => '../private/leak.pdf'], 'storage_path'],
            [['storage_path' => 'https://example.test/leak.pdf'], 'storage_path'],
            [['storage_path' => 'C:\\temp\\leak.pdf'], 'storage_path'],
            [['storage_path' => 'uploads/projects/file.pdf'], 'storage_path'],
            [['original_filename' => '../safe.pdf'], 'original_filename'],
        ];

        foreach ($cases as [$override, $errorField]) {
            $this->actingAs($sales)
                ->postJson(route('documents.store'), array_merge($basePayload, $override))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($errorField);
        }

        $this->assertDatabaseMissing('managed_documents', [
            'title' => 'Unsafe Storage Metadata Document',
        ]);
    }

    public function test_document_submission_rejects_unsafe_file_metadata_from_configured_policy(): void
    {
        $this->seed();

        Config::set('builder360.documents.allowed_extensions', ['pdf', 'jpg']);
        Config::set('builder360.documents.allowed_mime_types', ['application/pdf', 'image/jpeg']);
        Config::set('builder360.documents.max_file_size_kb', 100);

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $category = DocumentCategory::where('code', 'RERA_CERT')->firstOrFail();

        $basePayload = [
            'document_category_id' => $category->id,
            'title' => 'Unsafe File Metadata Document',
            'owner_type' => 'project',
            'owner_id' => $project->id,
            'storage_path' => 'documents/projects/safe.pdf',
            'original_filename' => 'safe.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 1000,
            'checksum_sha256' => hash('sha256', 'unsafe-file-metadata'),
            'issue_date' => now()->subDay()->toDateString(),
            'expires_on' => now()->addYear()->toDateString(),
        ];

        $cases = [
            [['mime_type' => 'application/x-php'], 'mime_type'],
            [['file_size_bytes' => 120 * 1024], 'file_size_bytes'],
            [['checksum_sha256' => str_repeat('z', 64)], 'checksum_sha256'],
            [['original_filename' => 'payload.php', 'storage_path' => 'documents/projects/payload.php'], 'original_filename'],
            [['storage_path' => 'documents/projects/safe.jpg'], 'storage_path'],
        ];

        foreach ($cases as [$override, $errorField]) {
            $this->actingAs($sales)
                ->postJson(route('documents.store'), array_merge($basePayload, $override))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($errorField);
        }

        $this->assertDatabaseMissing('managed_documents', [
            'title' => 'Unsafe File Metadata Document',
        ]);
    }

    public function test_document_file_policy_readiness_reports_safe_defaults(): void
    {
        $readiness = app(DocumentFilePolicy::class)->readiness();

        $this->assertSame('ok', $readiness['status']);
        $this->assertContains('pdf', $readiness['allowed_extensions']);
        $this->assertContains('application/pdf', $readiness['allowed_mime_types']);
        $this->assertSame(10240, $readiness['max_file_size_kb']);
        $this->assertSame([], $readiness['dangerous_extensions']);
        $this->assertSame([], $readiness['unsafe_mime_types']);
        $this->assertTrue($readiness['requirements']['sha256_checksum_supported']);
    }

    public function test_new_document_version_marks_previous_matching_document_not_current(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $project = Project::where('code', 'SKY-PUN')->firstOrFail();
        $category = DocumentCategory::where('code', 'RERA_CERT')->firstOrFail();
        $existing = ManagedDocument::where('document_number', 'DOC-1001')->firstOrFail();

        $response = $this->actingAs($sales)->postJson(route('documents.store'), [
            'document_category_id' => $category->id,
            'title' => $existing->title,
            'owner_type' => 'project',
            'owner_id' => $project->id,
            'storage_path' => 'documents/demo/skyline-rera-certificate-v2.pdf',
            'original_filename' => 'skyline-rera-certificate-v2.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 250000,
            'checksum_sha256' => hash('sha256', 'DOC-1001-v2'),
            'issue_date' => now()->toDateString(),
            'expires_on' => now()->addYear()->toDateString(),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.is_current', true);

        $this->assertDatabaseHas('managed_documents', [
            'id' => $existing->id,
            'is_current' => false,
        ]);
    }

    public function test_authorized_user_can_download_managed_document_file_and_audit_is_recorded(): void
    {
        $this->seed();

        Storage::fake('local');

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $document = ManagedDocument::where('document_number', 'DOC-1001')->firstOrFail();
        $contents = 'Controlled document payload';

        Storage::disk('local')->put($document->storage_path, $contents);

        $response = $this->actingAs($sales)->get(route('documents.download', $document));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', $document->mime_type)
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame($contents, $response->streamedContent());

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'documents.document.downloaded',
            'action' => 'Downloaded managed document',
            'user_id' => $sales->id,
            'auditable_type' => ManagedDocument::class,
            'auditable_id' => $document->id,
        ]);
    }

    public function test_authorized_document_download_returns_not_found_when_file_is_missing(): void
    {
        $this->seed();

        Storage::fake('local');

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $document = ManagedDocument::where('document_number', 'DOC-1001')->firstOrFail();

        $this->actingAs($sales)
            ->get(route('documents.download', $document))
            ->assertNotFound();

        $this->assertDatabaseMissing('audit_events', [
            'event_type' => 'documents.document.downloaded',
            'user_id' => $sales->id,
            'auditable_type' => ManagedDocument::class,
            'auditable_id' => $document->id,
        ]);
    }

    public function test_partner_cannot_access_internal_document_routes(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $this->actingAs($partner)
            ->getJson(route('documents.index'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->getJson(route('documents.categories.index'))
            ->assertForbidden();

        $document = ManagedDocument::where('document_number', 'DOC-1001')->firstOrFail();

        $this->actingAs($partner)
            ->get(route('documents.download', $document))
            ->assertForbidden();
    }
}
