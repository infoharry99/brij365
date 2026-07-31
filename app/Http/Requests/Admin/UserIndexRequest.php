<?php

namespace App\Http\Requests\Admin;

use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', \App\Models\User::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'suspended'])],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            app(QueryFilterPolicy::class)->rejectUnexpected(
                $validator,
                $this->query(),
                ['company_id', 'role_id', 'status', 'search', 'page'],
            );

            if ($validator->errors()->isNotEmpty() || ! $this->filled('company_id')) {
                return;
            }

            $user = $this->user();

            if (! $user || (! $user->hasPermission('*') && ($user->company_id === null || $this->integer('company_id') !== (int) $user->company_id))) {
                $validator->errors()->add('company_id', 'The selected company is outside your administration scope.');
            }
        });
    }
}
