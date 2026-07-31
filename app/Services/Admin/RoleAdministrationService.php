<?php

namespace App\Services\Admin;

use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleAdministrationService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createRole(array $data, User $actor, Request $request): Role
    {
        return DB::transaction(function () use ($data, $actor, $request): Role {
            $this->assertRoleScopeAllowed($actor, (string) $data['scope_level'], $data['permissions']);

            $role = Role::create([
                'slug' => $data['slug'],
                'name' => $data['name'],
                'scope_level' => $data['scope_level'],
                'permissions' => array_values($data['permissions']),
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->auditLogger->record(
                $actor,
                'admin.role.created',
                'Created role '.$role->name,
                $role,
                [
                    'slug' => $role->slug,
                    'scope_level' => $role->scope_level,
                    'permissions' => $role->permissions,
                    'is_active' => $role->is_active,
                ],
                $request,
            );

            return $role->loadCount('users');
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateRole(Role $role, array $data, User $actor, Request $request): Role
    {
        return DB::transaction(function () use ($role, $data, $actor, $request): Role {
            $nextScopeLevel = (string) ($data['scope_level'] ?? $role->scope_level);
            $nextPermissions = $data['permissions'] ?? ($role->permissions ?? []);

            $this->assertRoleScopeAllowed($actor, $nextScopeLevel, $nextPermissions);

            $before = [
                'slug' => $role->slug,
                'name' => $role->name,
                'scope_level' => $role->scope_level,
                'permissions' => $role->permissions ?? [],
                'is_active' => $role->is_active,
            ];

            $role->forceFill([
                'slug' => $data['slug'] ?? $role->slug,
                'name' => $data['name'] ?? $role->name,
                'scope_level' => $data['scope_level'] ?? $role->scope_level,
                'permissions' => isset($data['permissions']) ? array_values($data['permissions']) : ($role->permissions ?? []),
                'is_active' => $data['is_active'] ?? $role->is_active,
            ])->save();

            $after = [
                'slug' => $role->slug,
                'name' => $role->name,
                'scope_level' => $role->scope_level,
                'permissions' => $role->permissions ?? [],
                'is_active' => $role->is_active,
            ];

            $this->auditLogger->record(
                $actor,
                'admin.role.updated',
                'Updated role '.$role->name,
                $role,
                [
                    'before' => $before,
                    'after' => $after,
                ],
                $request,
            );

            return $role->loadCount('users');
        });
    }

    /**
     * @param array<int, string> $permissions
     */
    private function assertRoleScopeAllowed(User $actor, string $scopeLevel, array $permissions): void
    {
        if ($actor->hasPermission('*')) {
            return;
        }

        if ($scopeLevel === 'global') {
            throw ValidationException::withMessages([
                'scope_level' => 'Only a wildcard administrator can manage global-scope roles.',
            ]);
        }

        if (in_array('*', $permissions, true)) {
            throw ValidationException::withMessages([
                'permissions' => 'Only a wildcard administrator can manage wildcard roles.',
            ]);
        }
    }
}
