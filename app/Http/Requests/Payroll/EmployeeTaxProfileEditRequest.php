<?php

namespace App\Http\Requests\Payroll;

use App\Models\Employee;
use App\Models\EmployeeTaxProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class EmployeeTaxProfileEditRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = Employee::query()->where('user_id', $this->user()?->id)->first();

        return $employee !== null && $this->user()?->can('create', [EmployeeTaxProfile::class, $employee]) === true;
    }

    public function rules(): array
    {
        return ['financial_year' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/']];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateFinancialYear($validator, $this->input('financial_year'))];
    }

    public function financialYear(): string
    {
        if ($this->filled('financial_year')) {
            return (string) $this->validated('financial_year');
        }

        $today = now();
        $startYear = $today->month >= 4 ? $today->year : $today->year - 1;

        return $startYear.'-'.substr((string) ($startYear + 1), -2);
    }

    private function validateFinancialYear(Validator $validator, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! preg_match('/^(\d{4})-(\d{2})$/', (string) $value, $matches)
            || (int) $matches[2] !== (((int) $matches[1] + 1) % 100)) {
            $validator->errors()->add('financial_year', 'Financial year must use YYYY-YY with consecutive years.');
        }
    }
}
