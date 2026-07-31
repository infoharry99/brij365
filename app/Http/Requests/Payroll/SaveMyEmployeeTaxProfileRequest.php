<?php

namespace App\Http\Requests\Payroll;

use App\Application\Payroll\Data\EmployeeTaxProfileDraftData;
use App\Domain\Payroll\ValueObjects\MinorMoney;
use App\Models\Employee;
use App\Models\EmployeeTaxProfile;
use App\Models\ManagedDocument;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SaveMyEmployeeTaxProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $declarations = collect((array) $this->input('declarations', []))
            ->filter(fn (mixed $declaration): bool => is_array($declaration)
                && collect([
                    $declaration['category_code'] ?? null,
                    $declaration['declaration_type'] ?? null,
                    $declaration['declared_amount'] ?? null,
                    $declaration['managed_document_id'] ?? null,
                ])->contains(fn (mixed $value): bool => $value !== null && trim((string) $value) !== ''))
            ->values()
            ->all();

        $this->merge(['declarations' => $declarations]);
    }

    public function authorize(): bool
    {
        $employee = $this->employee();
        if ($employee === null) {
            return false;
        }

        $profile = $this->latestProfile($employee);

        return $profile === null
            ? $this->user()?->can('create', [EmployeeTaxProfile::class, $employee]) === true
            : $this->user()?->can('update', $profile) === true;
    }

    public function rules(): array
    {
        $maximum = app(MoneyInputPolicy::class)->hrAmountMaxRule();

        return [
            'financial_year' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'regime_code' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{2,64}$/'],
            'lock_version' => ['nullable', 'integer', 'min:0'],
            'previous_employer_income' => ['nullable', 'decimal:0,2', 'min:0', $maximum],
            'previous_employer_tds' => ['nullable', 'decimal:0,2', 'min:0', $maximum],
            'projected_other_income' => ['nullable', 'decimal:0,2', 'min:0', $maximum],
            'declarations' => ['nullable', 'array', 'max:50'],
            'declarations.*.category_code' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{2,64}$/'],
            'declarations.*.declaration_type' => ['required', Rule::in(['deduction', 'exemption', 'other_income'])],
            'declarations.*.declared_amount' => ['required', 'decimal:0,2', 'min:0', $maximum],
            'declarations.*.managed_document_id' => ['nullable', 'integer', Rule::exists('managed_documents', 'id')],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->validateFinancialYear($validator);
            $employee = $this->employee();
            if ($employee === null) {
                return;
            }

            $latest = $this->latestProfile($employee);
            if ($latest !== null && ! $this->filled('lock_version')) {
                $validator->errors()->add('lock_version', 'Refresh the tax profile before saving changes.');
            }

            $codes = [];
            foreach ((array) $this->input('declarations', []) as $index => $declaration) {
                $code = strtoupper(trim((string) ($declaration['category_code'] ?? '')));
                if ($code !== '' && isset($codes[$code])) {
                    $validator->errors()->add("declarations.$index.category_code", 'Declaration category codes must be unique.');
                }
                $codes[$code] = true;

                $documentId = $declaration['managed_document_id'] ?? null;
                if ($documentId === null || $documentId === '') {
                    continue;
                }
                $document = ManagedDocument::query()->find((int) $documentId);
                if ($document === null
                    || $document->company_id !== $employee->company_id
                    || $document->owner_type !== 'employee'
                    || (int) $document->owner_id !== $employee->id
                    || ! $document->is_current
                    || ! in_array($document->status, ['submitted', 'approved'], true)
                    || blank($document->checksum_sha256)
                    || (int) $document->version < 1
                    || $this->user()?->can('view', $document) !== true) {
                    $validator->errors()->add("declarations.$index.managed_document_id", 'The selected proof document is not available for this declaration.');
                }
            }
        }];
    }

    public function toData(): EmployeeTaxProfileDraftData
    {
        $validated = $this->validated();
        $employee = $this->employee();
        abort_unless($employee !== null, 403);

        return EmployeeTaxProfileDraftData::fromArray([
            'employee_id' => $employee->id,
            'financial_year' => $validated['financial_year'],
            'regime_code' => $validated['regime_code'],
            'lock_version' => $validated['lock_version'] ?? null,
            'previous_employer_income_minor' => $this->minor($validated['previous_employer_income'] ?? 0),
            'previous_employer_tds_minor' => $this->minor($validated['previous_employer_tds'] ?? 0),
            'projected_other_income_minor' => $this->minor($validated['projected_other_income'] ?? 0),
            'declarations' => array_map(fn (array $declaration): array => [
                'category_code' => $declaration['category_code'],
                'declaration_type' => $declaration['declaration_type'],
                'declared_minor' => $this->minor($declaration['declared_amount']),
                'managed_document_id' => $declaration['managed_document_id'] ?? null,
            ], $validated['declarations'] ?? []),
        ]);
    }

    private function employee(): ?Employee
    {
        return Employee::query()->where('user_id', $this->user()?->id)->first();
    }

    private function latestProfile(Employee $employee): ?EmployeeTaxProfile
    {
        if (! $this->filled('financial_year')) {
            return null;
        }

        return EmployeeTaxProfile::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->where('financial_year', $this->input('financial_year'))
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first();
    }

    private function validateFinancialYear(Validator $validator): void
    {
        $value = (string) $this->input('financial_year');
        if (! preg_match('/^(\d{4})-(\d{2})$/', $value, $matches)
            || (int) $matches[2] !== (((int) $matches[1] + 1) % 100)) {
            $validator->errors()->add('financial_year', 'Financial year must use YYYY-YY with consecutive years.');
        }
    }

    private function minor(int|string $value): int
    {
        return MinorMoney::fromDecimal($value)->minor;
    }
}
