<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Role::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', Rule::unique('roles', 'slug')],
            'name' => ['required', 'string', 'max:120'],
            'scope_level' => ['required', 'string', Rule::in(['global', 'company', 'department', 'project', 'self', 'readonly', 'partner'])],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['required', 'string', 'max:120', 'regex:/^(\*|[a-z0-9_.-]+)$/'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [$this->permissionGuard(...)];
    }

    protected function permissionGuard(Validator $validator): void
    {
        $actor = $this->user();
        $permissions = $this->collect('permissions')->filter()->values()->all();

        if (count($permissions) !== count(array_unique($permissions))) {
            $validator->errors()->add('permissions', 'Role permissions must be unique.');
        }

        if (! $actor) {
            return;
        }

        if (in_array('*', $permissions, true) && ! $actor->hasPermission('*')) {
            $validator->errors()->add('permissions', 'Only a global administrator can create wildcard roles.');
        }

        if ((string) $this->input('scope_level') === 'global' && ! $actor->hasPermission('*')) {
            $validator->errors()->add('scope_level', 'Only a wildcard administrator can create global-scope roles.');
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
