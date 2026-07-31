<?php

namespace App\Services\Payroll;

use App\Application\Payroll\Data\EmployeeTaxDeclarationDecisionData;
use App\Application\Payroll\Data\EmployeeTaxDeclarationDraftData;
use App\Application\Payroll\Data\EmployeeTaxProfileDraftData;
use App\Application\Payroll\Data\EmployeeTaxProfileVerificationData;
use App\Domain\Payroll\Services\CanonicalPayrollHasher;
use App\Domain\Payroll\Services\EmployeeTaxInputAccess;
use App\Domain\Payroll\Services\EmployeeTaxProfileCanonicalPayload;
use App\Models\Employee;
use App\Models\EmployeeTaxProfile;
use App\Models\ManagedDocument;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Security\CompanyScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EmployeeTaxInputService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CompanyScopeService $companyScope,
        private readonly CanonicalPayrollHasher $hasher,
        private readonly EmployeeTaxProfileCanonicalPayload $canonicalPayload,
        private readonly EmployeeTaxInputAccess $access,
    ) {}

    public function saveDraft(EmployeeTaxProfileDraftData $data, User $actor, ?Request $request = null): EmployeeTaxProfile
    {
        return DB::transaction(function () use ($data, $actor, $request): EmployeeTaxProfile {
            $employee = Employee::query()->whereKey($data->employeeId)->lockForUpdate()->firstOrFail();
            $this->assertCanEdit($actor, $employee);
            $financialYear = $this->financialYear($data->financialYear);
            $profile = EmployeeTaxProfile::query()
                ->where('company_id', $employee->company_id)
                ->where('employee_id', $employee->id)
                ->where('financial_year', $financialYear)
                ->orderByDesc('version')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            $createdNow = false;

            if ($profile !== null) {
                if ($profile->status === EmployeeTaxProfile::STATUS_LOCKED) {
                    $this->assertVersion($profile, $data->lockVersion);
                    $profile = $this->newDraft($employee, $actor, $financialYear, $data, $profile);
                    $createdNow = true;
                } elseif ($profile->status !== EmployeeTaxProfile::STATUS_DRAFT) {
                    throw ValidationException::withMessages(['tax_profile' => 'Only a draft tax profile can be edited.']);
                } else {
                    $this->assertVersion($profile, $data->lockVersion);
                }
            }

            $payload = $this->payload($data);
            $checksum = $this->hasher->hash([
                'employee_id' => $employee->id,
                'financial_year' => $financialYear,
                'regime_code' => $data->regimeCode,
                'input_payload' => $payload,
            ]);

            if ($profile === null) {
                $profile = $this->newDraft($employee, $actor, $financialYear, $data);
                $createdNow = true;
            }

            if (! $createdNow) {
                $history = $profile->workflow_history ?? [];
                $history[] = $this->history('draft', $actor, 'Employee tax inputs updated.');
                $profile->forceFill([
                    'regime_code' => $data->regimeCode,
                    'lock_version' => $profile->lock_version + 1,
                    'input_payload' => $payload,
                    'input_checksum' => $checksum,
                    'workflow_history' => $history,
                ])->save();
            }

            $this->syncDeclarations($profile, $data->declarations, $employee);
            $this->refreshChecksum($profile);
            $this->auditLogger->record($actor, 'payroll.employee_tax_profile.saved', 'Saved employee tax inputs', $profile, [
                'employee_id' => $employee->id,
                'financial_year' => $financialYear,
                'version' => $profile->version,
                'declaration_count' => count($data->declarations),
            ], $request);

            return $profile->load(['employee', 'declarations.proofDocument']);
        });
    }

    private function newDraft(
        Employee $employee,
        User $actor,
        string $financialYear,
        EmployeeTaxProfileDraftData $data,
        ?EmployeeTaxProfile $superseded = null,
    ): EmployeeTaxProfile {
        $payload = $this->payload($data);
        $version = $superseded === null ? 1 : $superseded->version + 1;

        return EmployeeTaxProfile::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'supersedes_employee_tax_profile_id' => $superseded?->id,
            'created_by_user_id' => $actor->id,
            'financial_year' => $financialYear,
            'regime_code' => $data->regimeCode,
            'status' => EmployeeTaxProfile::STATUS_DRAFT,
            'version' => $version,
            'input_payload' => $payload,
            'input_checksum' => $this->hasher->hash([
                'employee_id' => $employee->id,
                'financial_year' => $financialYear,
                'regime_code' => $data->regimeCode,
                'input_payload' => $payload,
            ]),
            'workflow_history' => [$this->history(
                $superseded === null ? 'draft' : 'amendment_started',
                $actor,
                $superseded === null
                    ? 'Employee tax inputs created.'
                    : 'A governed amendment was started from locked version '.$superseded->version.'.',
            )],
        ]);
    }

    public function submit(EmployeeTaxProfile $profile, User $actor, int $expectedLockVersion, ?Request $request = null): EmployeeTaxProfile
    {
        $profile->loadMissing('employee');
        $this->assertCanEdit($actor, $profile->employee);

        return $this->transition($profile, $actor, $expectedLockVersion, EmployeeTaxProfile::STATUS_DRAFT, EmployeeTaxProfile::STATUS_SUBMITTED, 'submitted', function (EmployeeTaxProfile $locked) use ($actor): array {
            if ($locked->declarations()->where('status', 'draft')->exists()) {
                throw ValidationException::withMessages(['declarations' => 'All declarations must be ready for verification before submission.']);
            }

            return ['submitted_by_user_id' => $actor->id, 'submitted_at' => now()];
        }, $request);
    }

    public function verify(EmployeeTaxProfile $profile, User $actor, EmployeeTaxProfileVerificationData $verification, ?Request $request = null): EmployeeTaxProfile
    {
        $this->assertPayrollManager($actor, 'verify');

        return $this->transition($profile, $actor, $verification->lockVersion, EmployeeTaxProfile::STATUS_SUBMITTED, EmployeeTaxProfile::STATUS_VERIFIED, 'verified', function (EmployeeTaxProfile $locked) use ($actor, $verification): array {
            if (in_array($actor->id, [$locked->created_by_user_id, $locked->submitted_by_user_id], true)) {
                throw ValidationException::withMessages(['tax_profile' => 'The tax-input creator or submitter cannot verify the same profile.']);
            }

            $this->applyDeclarationDecisions($locked, $verification->decisions);
            $pending = $locked->declarations()->whereNotIn('status', ['verified', 'rejected'])->exists();
            if ($pending) {
                throw ValidationException::withMessages(['declarations' => 'Every declaration must be verified or rejected before the profile can be verified.']);
            }

            $this->refreshChecksum($locked);

            return ['verified_by_user_id' => $actor->id, 'verified_at' => now()];
        }, $request);
    }

    public function lock(EmployeeTaxProfile $profile, User $actor, int $expectedLockVersion, ?Request $request = null): EmployeeTaxProfile
    {
        if (! $this->access->hasAnyExplicit($actor, ['payroll.approve', 'compliance.manage'])) {
            throw ValidationException::withMessages(['tax_profile' => 'You are not authorized to lock employee tax inputs.']);
        }

        return $this->transition($profile, $actor, $expectedLockVersion, EmployeeTaxProfile::STATUS_VERIFIED, EmployeeTaxProfile::STATUS_LOCKED, 'locked', function (EmployeeTaxProfile $locked) use ($actor): array {
            if (in_array($actor->id, [$locked->created_by_user_id, $locked->submitted_by_user_id, $locked->verified_by_user_id], true)) {
                throw ValidationException::withMessages(['tax_profile' => 'The creator, submitter, or verifier cannot lock the same employee tax profile.']);
            }

            return ['locked_by_user_id' => $actor->id, 'locked_at' => now()];
        }, $request);
    }

    /**
     * @param callable(EmployeeTaxProfile): array<string, mixed> $attributes
     */
    private function transition(EmployeeTaxProfile $profile, User $actor, int $expectedLockVersion, string $from, string $to, string $event, callable $attributes, ?Request $request): EmployeeTaxProfile
    {
        return DB::transaction(function () use ($profile, $actor, $expectedLockVersion, $from, $to, $event, $attributes, $request): EmployeeTaxProfile {
            $locked = EmployeeTaxProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $this->assertCompanyScope($actor, $locked->company_id);
            $this->assertVersion($locked, $expectedLockVersion);
            if ($locked->status !== $from) {
                throw ValidationException::withMessages(['tax_profile' => 'The employee tax profile is not in the required '.$from.' state.']);
            }

            $history = $locked->workflow_history ?? [];
            $history[] = $this->history($event, $actor, 'Employee tax inputs '.$event.'.');
            $locked->forceFill($attributes($locked) + [
                'status' => $to,
                'lock_version' => $locked->lock_version + 1,
                'workflow_history' => $history,
            ])->save();
            $this->auditLogger->record($actor, 'payroll.employee_tax_profile.'.$event, 'Employee tax inputs '.$event, $locked, [
                'employee_id' => $locked->employee_id,
                'financial_year' => $locked->financial_year,
                'version' => $locked->version,
            ], $request);

            return $locked->load(['employee', 'declarations.proofDocument']);
        });
    }

    /** @return array<string, int> */
    private function payload(EmployeeTaxProfileDraftData $data): array
    {
        return [
            'previous_employer_income_minor' => $data->previousEmployerIncomeMinor,
            'previous_employer_tds_minor' => $data->previousEmployerTdsMinor,
            'projected_other_income_minor' => $data->projectedOtherIncomeMinor,
        ];
    }

    /** @param list<EmployeeTaxDeclarationDraftData> $declarations */
    private function syncDeclarations(EmployeeTaxProfile $profile, array $declarations, Employee $employee): void
    {
        $seen = [];
        foreach ($declarations as $index => $declaration) {
            $code = $declaration->categoryCode;
            if ($code === '' || ! preg_match('/^[A-Z0-9_\-]{2,64}$/', $code) || isset($seen[$code])) {
                throw ValidationException::withMessages(["declarations.$index.category_code" => 'Declaration category codes must be unique stable identifiers.']);
            }
            $seen[$code] = true;
            $type = $declaration->declarationType;
            if (! in_array($type, ['deduction', 'exemption', 'other_income'], true)) {
                throw ValidationException::withMessages(["declarations.$index.declaration_type" => 'Declaration type must be deduction, exemption, or other_income.']);
            }
            $amount = $declaration->declaredMinor;

            $documentId = $declaration->managedDocumentId;
            $proofSnapshot = null;
            if ($documentId !== null) {
                $validDocument = ManagedDocument::query()
                    ->whereKey($documentId)
                    ->where('company_id', $employee->company_id)
                    ->where('owner_type', 'employee')
                    ->where('owner_id', $employee->id)
                    ->whereIn('status', ['submitted', 'approved'])
                    ->where('is_current', true)
                    ->first();
                if ($validDocument === null || blank($validDocument->checksum_sha256) || (int) $validDocument->version < 1) {
                    throw ValidationException::withMessages(["declarations.$index.managed_document_id" => 'The selected proof document is not an authorized document for this employee.']);
                }

                $proofSnapshot = [
                    'managed_document_id' => $validDocument->id,
                    'document_number' => $validDocument->document_number,
                    'version' => (int) $validDocument->version,
                    'checksum_sha256' => $validDocument->checksum_sha256,
                ];
            }

            $profile->declarations()->updateOrCreate(['category_code' => $code], [
                'managed_document_id' => $documentId,
                'declaration_type' => $type,
                'status' => 'submitted',
                'amount_payload' => ['declared_minor' => $amount, 'verified_minor' => null],
                'decision_note' => null,
                'metadata' => [
                    'source' => 'employee_tax_input_service',
                    'proof_snapshot' => $proofSnapshot,
                ],
            ]);
        }

        $profile->declarations()->whereNotIn('category_code', array_keys($seen))->delete();
    }

    /** @param list<EmployeeTaxDeclarationDecisionData> $decisions */
    private function applyDeclarationDecisions(EmployeeTaxProfile $profile, array $decisions): void
    {
        $byCode = collect($decisions)->keyBy(fn (EmployeeTaxDeclarationDecisionData $decision): string => $decision->categoryCode);
        foreach ($profile->declarations()->lockForUpdate()->get() as $declaration) {
            $decision = $byCode->get($declaration->category_code);
            if (! $decision instanceof EmployeeTaxDeclarationDecisionData) {
                throw ValidationException::withMessages(['declarations' => 'A verification decision is required for every submitted declaration.']);
            }

            $status = $decision->status;
            if (! in_array($status, ['verified', 'rejected'], true)) {
                throw ValidationException::withMessages(['declarations' => 'Declaration decisions must be verified or rejected.']);
            }
            $declaredMinor = (int) data_get($declaration->amount_payload, 'declared_minor', 0);
            $verifiedMinor = $status === 'verified' ? $decision->verifiedMinor : 0;
            if ($verifiedMinor < 0 || $verifiedMinor > $declaredMinor) {
                throw ValidationException::withMessages(['declarations' => 'Verified declaration amounts must be non-negative minor units and cannot exceed the declared amount.']);
            }
            $note = $decision->decisionNote;
            if ($status === 'rejected' && $note === null) {
                throw ValidationException::withMessages(['declarations' => 'Rejected declarations require a decision note.']);
            }

            $declaration->forceFill([
                'status' => $status,
                'amount_payload' => ['declared_minor' => $declaredMinor, 'verified_minor' => $verifiedMinor],
                'decision_note' => $note,
            ])->save();
        }
    }

    private function refreshChecksum(EmployeeTaxProfile $profile): void
    {
        $profile->load('declarations');
        $profile->forceFill([
            'input_checksum' => $this->hasher->hash($this->canonicalPayload->for($profile)),
        ])->save();
    }

    private function assertCanEdit(User $actor, Employee $employee): void
    {
        $self = $employee->user_id === $actor->id && $this->access->hasAnyExplicit($actor, ['employee.self_service']);
        if (! $self && ! $this->access->hasAnyExplicit($actor, ['payroll.manage'])) {
            throw ValidationException::withMessages(['employee_id' => 'You are not authorized to manage this employee tax profile.']);
        }
        $this->assertCompanyScope($actor, $employee->company_id);
    }

    private function assertPayrollManager(User $actor, string $action): void
    {
        if (! $this->access->hasAnyExplicit($actor, ['payroll.manage', 'compliance.manage'])) {
            throw ValidationException::withMessages(['tax_profile' => 'You are not authorized to '.$action.' employee tax inputs.']);
        }
    }

    private function assertCompanyScope(User $actor, int $companyId): void
    {
        if (! $this->companyScope->allows($actor, $companyId)) {
            throw ValidationException::withMessages(['tax_profile' => 'The employee tax profile is outside your company scope.']);
        }
    }

    private function assertVersion(EmployeeTaxProfile $profile, mixed $version): void
    {
        if (! is_int($version) || $version !== $profile->lock_version) {
            throw ValidationException::withMessages(['lock_version' => 'The employee tax profile was changed in another session. Refresh before continuing.']);
        }
    }

    private function financialYear(string $value): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $value, $matches) || ((int) $matches[2]) !== (((int) $matches[1] + 1) % 100)) {
            throw ValidationException::withMessages(['financial_year' => 'Financial year must use YYYY-YY with consecutive years.']);
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private function history(string $event, User $actor, string $note): array
    {
        return ['event' => $event, 'user_id' => $actor->id, 'at' => now()->toISOString(), 'note' => $note];
    }
}
