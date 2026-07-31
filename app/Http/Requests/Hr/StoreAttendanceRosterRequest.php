<?php

namespace App\Http\Requests\Hr;

use App\Models\AttendanceRoster;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAttendanceRosterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AttendanceRoster::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'name' => ['required', 'string', 'max:160'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'timezone' => ['nullable', 'timezone'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            if (! $user) {
                return;
            }

            $companyId = $this->filled('company_id')
                ? $this->integer('company_id')
                : app(CompanyScopeService::class)->companyIdFor($user);

            if ($companyId === null || $companyId === 0) {
                $validator->errors()->add('company_id', 'A company assignment is required before creating a roster.');

                return;
            }

            if (! app(CompanyScopeService::class)->allows($user, $companyId)) {
                $validator->errors()->add('company_id', 'The selected company is outside your company scope.');
            }
        }];
    }
}
