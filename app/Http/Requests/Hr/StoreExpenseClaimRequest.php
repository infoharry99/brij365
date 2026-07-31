<?php

namespace App\Http\Requests\Hr;

use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Services\Security\CompanyScopeService;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExpenseClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ExpenseClaim::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'claim_type' => ['required', 'string', Rule::in(['travel', 'food', 'fuel', 'mobile', 'medical', 'office', 'other'])],
            'claim_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:1', app(MoneyInputPolicy::class)->hrAmountMaxRule()],
            'currency' => ['nullable', 'string', Rule::in(['INR'])],
            'description' => ['required', 'string', 'min:10', 'max:255'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*.name' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.url' => ['required_with:attachments', 'string', 'max:1024'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $employee = Employee::find($this->integer('employee_id'));
                $actor = $this->user();

                if (! $employee || ! $actor) {
                    return;
                }

                $selfServiceOnly = $actor->hasPermission('employee.self_service')
                    && ! $actor->hasPermission('claims.manage')
                    && ! $actor->hasPermission('hr.manage');

                if ($selfServiceOnly && (int) $employee->user_id !== (int) $actor->id) {
                    $validator->errors()->add('employee_id', 'Employees can submit claims only for their own profile.');

                    return;
                }

                if (! app(CompanyScopeService::class)->allows($actor, $employee->company_id)) {
                    $validator->errors()->add('employee_id', 'The selected employee is not available in your company.');
                }
            },
        ];
    }
}
