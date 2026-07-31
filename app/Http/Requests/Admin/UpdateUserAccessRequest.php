<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $managedUser = $this->route('user');

        return $managedUser instanceof \App\Models\User
            && $this->user()?->can('updateAccess', $managedUser) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('is_active', true)],
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'suspended'])],
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
                    $validator->errors()->add('company_id', 'Company administrators can keep users only in their own company.');
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
