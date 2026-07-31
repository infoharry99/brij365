<?php

namespace App\Http\Requests\Payroll;

use App\Application\Payroll\Data\EmployeeTaxProfileVerificationData;
use App\Domain\Payroll\ValueObjects\MinorMoney;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class VerifyEmployeeTaxProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('decisions')) {
            $this->merge(['decisions' => []]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('verify', $this->route('employeeTaxProfile')) === true;
    }

    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:0'],
            'decisions' => ['present', 'array', 'max:50'],
            'decisions.*.category_code' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{2,64}$/', 'distinct:ignore_case'],
            'decisions.*.status' => ['required', Rule::in(['verified', 'rejected'])],
            'decisions.*.verified_amount' => ['nullable', 'decimal:0,2', 'min:0', app(MoneyInputPolicy::class)->hrAmountMaxRule()],
            'decisions.*.decision_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('decisions', []) as $index => $decision) {
                if (($decision['status'] ?? null) === 'rejected'
                    && trim((string) ($decision['decision_note'] ?? '')) === '') {
                    $validator->errors()->add("decisions.$index.decision_note", 'A reason is required when a declaration is rejected.');
                }
            }
        }];
    }

    public function toData(): EmployeeTaxProfileVerificationData
    {
        $validated = $this->validated();

        return EmployeeTaxProfileVerificationData::fromArray([
            'lock_version' => $validated['lock_version'],
            'decisions' => array_map(fn (array $decision): array => [
                'category_code' => $decision['category_code'],
                'status' => $decision['status'],
                'verified_minor' => MinorMoney::fromDecimal($decision['verified_amount'] ?? 0)->minor,
                'decision_note' => $decision['decision_note'] ?? null,
            ], $validated['decisions']),
        ]);
    }
}
