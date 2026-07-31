<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role
            && $this->user()?->can('update', $role) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $roleId = $this->route('role')?->id;

        return [
            'slug' => ['sometimes', 'required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', Rule::unique('roles', 'slug')->ignore($roleId)],
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'scope_level' => ['sometimes', 'required', 'string', Rule::in(['global', 'company', 'department', 'project', 'self', 'readonly', 'partner'])],
            'permissions' => ['sometimes', 'required', 'array', 'min:1'],
            'permissions.*' => ['required', 'string', 'max:120', 'regex:/^(\*|[a-z0-9_.-]+)$/'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [$this->permissionGuard(...)];
    }

    protected function permissionGuard(Validator $validator): void
    {
        $actor = $this->user();
        $role = $this->route('role');
        $permissions = $this->has('permissions')
            ? $this->collect('permissions')->filter()->values()->all()
            : ($role instanceof Role ? ($role->permissions ?? []) : []);

        if (count($permissions) !== count(array_unique($permissions))) {
            $validator->errors()->add('permissions', 'Role permissions must be unique.');
        }

        if (! $actor) {
            return;
        }

        if ($this->has('is_active') && ! $this->boolean('is_active') && $role instanceof Role && $role->id === $actor->role_id) {
            $validator->errors()->add('is_active', 'Administrators cannot deactivate their own role.');
        }

        if (in_array('*', $permissions, true) && ! $actor->hasPermission('*')) {
            $validator->errors()->add('permissions', 'Only a global administrator can maintain wildcard roles.');
        }

        $scopeLevel = $this->has('scope_level')
            ? (string) $this->input('scope_level')
            : ($role instanceof Role ? $role->scope_level : null);

        if ($scopeLevel === 'global' && ! $actor->hasPermission('*')) {
            $validator->errors()->add('scope_level', 'Only a wildcard administrator can maintain global-scope roles.');
        }

        if ($actor->hasPermission('*')) {
            return;
        }

        foreach ($permissions as $permission) {
            if (! $actor->hasPermission($permission)) {
                $validator->errors()->add('permissions', 'Non-global administrators can grant only permissions already assigned to their own role.');
                break;
            }
        }
    }
}
