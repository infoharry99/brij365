<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'password' => PasswordPolicy::rules(confirmed: false),
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'suspended'])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $actor = $this->user();

                if (! $actor) {
                    return;
                }

                if (! $actor->hasPermission('*') && ($actor->company_id === null || $this->integer('company_id') !== (int) $actor->company_id)) {
                    $validator->errors()->add('company_id', 'Company administrators can create users only in their own company.');
                }

                $role = Role::find($this->integer('role_id'));

                if (! $role) {
                    return;
                }

                if (in_array('*', $role->permissions ?? [], true) && ! $actor->hasPermission('*')) {
                    $validator->errors()->add('role_id', 'Only a global administrator can assign a wildcard role.');
                }
            },
        ];
    }
}
