<?php

namespace Tests\Feature;

use App\Domain\Payroll\Services\AnnualTaxProjectionContextFactory;
use App\Domain\Payroll\Services\EmployeeTaxInputRegister;
use App\Http\Resources\ManagedDocumentResource;
use App\Models\Company;
use App\Models\DocumentCategory;
use App\Models\Employee;
use App\Models\EmployeeTaxDeclaration;
use App\Models\EmployeeTaxProfile;
use App\Models\ManagedDocument;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EmployeeTaxProfileWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_tax_inputs_follow_independent_review_locking_and_checksum_validation(): void
    {
        Storage::fake('local');
        $fixture = $this->fixture();

        $this->actingAs($fixture['employee_user'])
            ->get(route('hr.employees.me.tax-inputs.edit', ['financial_year' => '2026-27']))
            ->assertOk()
            ->assertSee('My tax declarations');

        $this->actingAs($fixture['employee_user'])
            ->put(route('hr.employees.me.tax-inputs.update'), $this->draftPayload($fixture['proof']->id))
            ->assertRedirect(route('hr.employees.me.tax-inputs.edit', ['financial_year' => '2026-27']))
            ->assertSessionHasNoErrors();

        $profile = EmployeeTaxProfile::query()->where('employee_id', $fixture['employee']->id)->firstOrFail();
        $declaration = $profile->declarations()->firstOrFail();
        $rawProfilePayload = (string) DB::table('employee_tax_profiles')->where('id', $profile->id)->value('input_payload');
        $rawDeclarationPayload = (string) DB::table('employee_tax_declarations')->where('id', $declaration->id)->value('amount_payload');

        $this->assertStringNotContainsString('12345.67', $rawProfilePayload);
        $this->assertStringNotContainsString('1000.00', $rawDeclarationPayload);
        $this->assertSame($fixture['proof']->checksum_sha256, data_get($declaration->metadata, 'proof_snapshot.checksum_sha256'));

        $this->actingAs($fixture['employee_user'])
            ->patch(route('hr.employees.me.tax-inputs.submit', $profile), ['lock_version' => 0])
            ->assertRedirect(route('hr.employees.me.tax-inputs.edit', ['financial_year' => '2026-27']))
            ->assertSessionHasNoErrors();

        $profile->refresh();
        $this->assertSame(EmployeeTaxProfile::STATUS_SUBMITTED, $profile->status);
        $this->assertSame(1, $profile->lock_version);

        $this->actingAs($fixture['verifier'])
            ->patch(route('payroll.employee-tax-profiles.verify', $profile), [
                'lock_version' => 1,
                'decisions' => [[
                    'category_code' => 'DECL_A',
                    'status' => 'verified',
                    'verified_amount' => '1000.00',
                    'decision_note' => 'Proof and amount independently checked.',
                ]],
            ])
            ->assertRedirect(route('payroll.employee-tax-profiles.show', $profile))
            ->assertSessionHasNoErrors();

        $profile->refresh();
        $this->assertSame(EmployeeTaxProfile::STATUS_VERIFIED, $profile->status);
        $this->assertSame(2, $profile->lock_version);

        $this->actingAs($fixture['verifier'])
            ->patch(route('payroll.employee-tax-profiles.lock', $profile), ['lock_version' => 2])
            ->assertForbidden();

        $this->actingAs($fixture['locker'])
            ->patch(route('payroll.employee-tax-profiles.lock', $profile), ['lock_version' => 2])
            ->assertRedirect(route('payroll.employee-tax-profiles.show', $profile))
            ->assertSessionHasNoErrors();

        $profile->refresh();
        $this->assertSame(EmployeeTaxProfile::STATUS_LOCKED, $profile->status);
        $this->assertSame(3, $profile->lock_version);

        $context = app(AnnualTaxProjectionContextFactory::class)->build(
            $fixture['company']->id,
            $fixture['employee']->id,
            Carbon::parse('2026-07-01'),
            $this->taxDefinition(),
        );
        $this->assertSame($profile->id, $context->employeeTaxProfileId);
        $this->assertSame(100000, $context->verifiedDeductionMinor);

        try {
            $fixture['proof']->delete();
            $this->fail('A proof pinned to a tax declaration must not be deleted.');
        } catch (\LogicException $exception) {
            $this->assertSame('A document pinned as employee tax proof cannot be deleted.', $exception->getMessage());
        }

        try {
            $fixture['proof']->forceDelete();
            $this->fail('A proof pinned to a tax declaration must not be force deleted.');
        } catch (\LogicException $exception) {
            $this->assertSame('A document pinned as employee tax proof cannot be deleted.', $exception->getMessage());
        }

        ManagedDocument::withoutEvents(fn () => $fixture['proof']->forceDelete());
        $this->assertNull($declaration->fresh()->managed_document_id);

        $contextAfterPrivilegedDocumentRemoval = app(AnnualTaxProjectionContextFactory::class)->build(
            $fixture['company']->id,
            $fixture['employee']->id,
            Carbon::parse('2026-07-01'),
            $this->taxDefinition(),
        );
        $this->assertSame($profile->input_checksum, $contextAfterPrivilegedDocumentRemoval->employeeTaxProfileChecksum);

        $metadata = (array) $declaration->fresh()->metadata;
        data_set($metadata, 'proof_snapshot.checksum_sha256', str_repeat('0', 64));
        DB::table('employee_tax_declarations')->where('id', $declaration->id)->update([
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);

        $this->expectException(ValidationException::class);
        app(AnnualTaxProjectionContextFactory::class)->build(
            $fixture['company']->id,
            $fixture['employee']->id,
            Carbon::parse('2026-07-01'),
            $this->taxDefinition(),
        );
    }

    public function test_locked_profile_amendment_preserves_history_and_rejects_stale_versions(): void
    {
        Storage::fake('local');
        $fixture = $this->fixture();
        $locked = $this->createLockedProfile($fixture);
        $lockedChecksum = $locked->input_checksum;

        $this->actingAs($fixture['employee_user'])
            ->put(route('hr.employees.me.tax-inputs.update'), $this->draftPayload($fixture['proof']->id, 3, '15000.00'))
            ->assertRedirect(route('hr.employees.me.tax-inputs.edit', ['financial_year' => '2026-27']))
            ->assertSessionHasNoErrors();

        $amendment = EmployeeTaxProfile::query()
            ->where('employee_id', $fixture['employee']->id)
            ->where('financial_year', '2026-27')
            ->latest('version')
            ->firstOrFail();

        $this->assertNotSame($locked->id, $amendment->id);
        $this->assertSame(2, $amendment->version);
        $this->assertSame($locked->id, $amendment->supersedes_employee_tax_profile_id);
        $this->assertSame(EmployeeTaxProfile::STATUS_DRAFT, $amendment->status);
        $this->assertSame($lockedChecksum, $locked->fresh()->input_checksum);

        $this->actingAs($fixture['employee_user'])
            ->from(route('hr.employees.me.tax-inputs.edit', ['financial_year' => '2026-27']))
            ->put(route('hr.employees.me.tax-inputs.update'), $this->draftPayload($fixture['proof']->id, 3, '16000.00'))
            ->assertRedirect(route('hr.employees.me.tax-inputs.edit', ['financial_year' => '2026-27']))
            ->assertSessionHasErrors('lock_version');

        $this->assertSame(1500000, (int) data_get($amendment->fresh()->input_payload, 'previous_employer_income_minor'));
    }

    public function test_sensitive_tax_profiles_and_proofs_require_explicit_payroll_or_compliance_access(): void
    {
        Storage::fake('local');
        $fixture = $this->fixture();

        $this->actingAs($fixture['employee_user'])
            ->put(route('hr.employees.me.tax-inputs.update'), $this->draftPayload($fixture['proof']->id))
            ->assertSessionHasNoErrors();

        $profile = EmployeeTaxProfile::query()->where('employee_id', $fixture['employee']->id)->firstOrFail();
        $restrictedUsers = [
            $this->createUser($fixture['company'], 'payroll_view_only', ['payroll.view']),
            $this->createUser($fixture['company'], 'hr_manager_view_only', ['hr.manage', 'payroll.view', 'documents.approve']),
            $this->createUser($fixture['company'], 'wildcard_technical', ['*']),
        ];

        foreach ($restrictedUsers as $restricted) {
            $this->actingAs($restricted)
                ->get(route('payroll.employee-tax-profiles.show', $profile))
                ->assertForbidden();
            $this->actingAs($restricted)
                ->get(route('documents.download', $fixture['proof']))
                ->assertForbidden();
        }

        $this->actingAs($fixture['employee_user'])
            ->get(route('documents.download', $fixture['proof']))
            ->assertOk();
        $this->actingAs($fixture['verifier'])
            ->get(route('documents.download', $fixture['proof']))
            ->assertOk();

        $complianceViewer = $this->createUser($fixture['company'], 'tax_compliance_viewer', ['compliance.view']);
        $this->actingAs($complianceViewer)
            ->get(route('payroll.employee-tax-profiles.show', $profile))
            ->assertOk();
        $this->actingAs($complianceViewer)
            ->get(route('documents.download', $fixture['proof']))
            ->assertOk();
        $this->actingAs($complianceViewer)
            ->patchJson(route('documents.approve', $fixture['proof']), ['approval_note' => 'Read-only reviewer.'])
            ->assertForbidden();

        $wildcard = $restrictedUsers[2];
        try {
            app(EmployeeTaxInputRegister::class)->review($wildcard, []);
            $this->fail('The domain read boundary must reject wildcard-only technical access.');
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $this->actingAs($wildcard)
            ->getJson(route('documents.index'))
            ->assertOk()
            ->assertJsonMissing(['id' => $fixture['proof']->id]);

        $request = Request::create('/documents/'.$fixture['proof']->id, 'GET');
        $request->setUserResolver(fn (): User => $wildcard);
        $restrictedResource = (new ManagedDocumentResource($fixture['proof']))->resolve($request);
        $this->assertArrayNotHasKey('storage_disk', $restrictedResource);
        $this->assertArrayNotHasKey('storage_path', $restrictedResource);
        $this->assertArrayNotHasKey('checksum_sha256', $restrictedResource);
        $this->assertArrayNotHasKey('metadata', $restrictedResource);
        $this->assertNull($restrictedResource['download_url']);

        $reviewRequest = Request::create('/documents/'.$fixture['proof']->id, 'GET');
        $reviewRequest->setUserResolver(fn (): User => $fixture['verifier']);
        $reviewResource = (new ManagedDocumentResource($fixture['proof']))->resolve($reviewRequest);
        $this->assertSame('local', $reviewResource['storage_disk']);
        $this->assertSame($fixture['proof']->storage_path, $reviewResource['storage_path']);
        $this->assertSame($fixture['proof']->checksum_sha256, $reviewResource['checksum_sha256']);

        $hrManager = $restrictedUsers[1];
        $this->actingAs($hrManager)
            ->patchJson(route('documents.approve', $fixture['proof']), ['approval_note' => 'Not authorized.'])
            ->assertForbidden();

        $fixture['proof']->forceFill(['uploaded_by_user_id' => $fixture['verifier']->id])->save();
        $this->actingAs($fixture['verifier'])
            ->patchJson(route('documents.approve', $fixture['proof']), ['approval_note' => 'Self approval.'])
            ->assertForbidden();

        $complianceManager = $this->createUser($fixture['company'], 'tax_compliance_manager', ['compliance.manage']);
        $this->actingAs($complianceManager)
            ->patchJson(route('documents.approve', $fixture['proof']), ['approval_note' => 'Independently approved.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    /** @return array{company: Company, employee_user: User, employee: Employee, verifier: User, locker: User, proof: ManagedDocument} */
    private function fixture(): array
    {
        $this->seed();
        $company = Company::query()->where('code', 'B360D')->firstOrFail();
        $employeeUser = $this->createUser($company, 'tax_employee_self_service', ['employee.self_service']);
        $verifier = $this->createUser($company, 'tax_payroll_verifier', ['payroll.manage']);
        $locker = $this->createUser($company, 'tax_payroll_locker', ['payroll.approve']);
        $employee = Employee::create([
            'company_id' => $company->id,
            'user_id' => $employeeUser->id,
            'employee_code' => 'TX-'.str_pad((string) $employeeUser->id, 6, '0', STR_PAD_LEFT),
            'name' => $employeeUser->name,
            'designation' => 'Tax Test Analyst',
            'department' => 'Finance',
            'employment_type' => 'full_time',
            'status' => 'active',
            'joined_on' => '2024-04-01',
            'statutory_state' => 'MH',
        ]);
        $contents = 'Immutable tax proof content';
        $path = 'employee-tax-proofs/'.$employee->id.'/declaration.pdf';
        Storage::disk('local')->put($path, $contents);
        $proof = ManagedDocument::create([
            'company_id' => $company->id,
            'document_category_id' => DocumentCategory::query()->where('code', 'EMPLOYEE_KYC')->firstOrFail()->id,
            'uploaded_by_user_id' => $employeeUser->id,
            'document_number' => 'TAX-PROOF-'.$employee->id,
            'title' => 'Employee tax declaration proof',
            'owner_type' => 'employee',
            'owner_id' => $employee->id,
            'status' => 'submitted',
            'storage_disk' => 'local',
            'storage_path' => $path,
            'original_filename' => 'declaration.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => strlen($contents),
            'checksum_sha256' => hash('sha256', $contents),
            'version' => 1,
            'is_current' => true,
            'metadata' => ['source' => 'employee_tax_profile_workflow_test'],
        ]);

        return compact('company', 'employeeUser', 'employee', 'verifier', 'locker', 'proof') + [
            'employee_user' => $employeeUser,
        ];
    }

    /** @return array<string, mixed> */
    private function draftPayload(int $proofId, ?int $lockVersion = null, string $previousIncome = '12345.67'): array
    {
        return [
            'financial_year' => '2026-27',
            'regime_code' => 'GOVERNED_DEFAULT',
            'lock_version' => $lockVersion,
            'previous_employer_income' => $previousIncome,
            'previous_employer_tds' => '1200.00',
            'projected_other_income' => '50.00',
            'declarations' => [[
                'category_code' => 'DECL_A',
                'declaration_type' => 'deduction',
                'declared_amount' => '1000.00',
                'managed_document_id' => $proofId,
            ]],
        ];
    }

    /** @param array<string, mixed> $fixture */
    private function createLockedProfile(array $fixture): EmployeeTaxProfile
    {
        $this->actingAs($fixture['employee_user'])
            ->put(route('hr.employees.me.tax-inputs.update'), $this->draftPayload($fixture['proof']->id))
            ->assertSessionHasNoErrors();
        $profile = EmployeeTaxProfile::query()->where('employee_id', $fixture['employee']->id)->firstOrFail();

        $this->actingAs($fixture['employee_user'])
            ->patch(route('hr.employees.me.tax-inputs.submit', $profile), ['lock_version' => 0])
            ->assertSessionHasNoErrors();
        $this->actingAs($fixture['verifier'])
            ->patch(route('payroll.employee-tax-profiles.verify', $profile), [
                'lock_version' => 1,
                'decisions' => [[
                    'category_code' => 'DECL_A',
                    'status' => 'verified',
                    'verified_amount' => '1000.00',
                    'decision_note' => 'Verified.',
                ]],
            ])
            ->assertSessionHasNoErrors();
        $this->actingAs($fixture['locker'])
            ->patch(route('payroll.employee-tax-profiles.lock', $profile), ['lock_version' => 2])
            ->assertSessionHasNoErrors();

        return $profile->refresh();
    }

    /** @return array<string, mixed> */
    private function taxDefinition(): array
    {
        return [
            'code' => 'TDS',
            'basis_codes' => [],
            'projection' => [
                'financial_year_start_month' => 4,
                'regime_slabs' => ['GOVERNED_DEFAULT' => []],
                'withholding_component_codes' => [],
            ],
        ];
    }

    /** @param list<string> $permissions */
    private function createUser(Company $company, string $key, array $permissions): User
    {
        $role = Role::create([
            'slug' => $key,
            'name' => str($key)->replace('_', ' ')->title()->toString(),
            'scope_level' => 'company',
            'permissions' => $permissions,
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'company_id' => $company->id,
            'email' => $key.'@example.test',
            'status' => 'active',
        ]);
    }
}
