<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserAdministrationService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createUser(array $data, User $actor, Request $request): User
    {
        return DB::transaction(function () use ($data, $actor, $request): User {
            $this->assertAssignableCompany($actor, (int) $data['company_id']);

            $user = User::create([
                'company_id' => $data['company_id'],
                'role_id' => $data['role_id'],
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'status' => $data['status'] ?? 'active',
                'email_verified_at' => null,
            ]);

            // if ($user->status === 'active') {
            //     $user->sendEmailVerificationNotification();
            // }

            $this->auditLogger->record(
                $actor,
                'admin.user.created',
                'Created user account '.$user->email,
                $user,
                [
                    'company_id' => $user->company_id,
                    'role_id' => $user->role_id,
                    'status' => $user->status,
                ],
                $request,
            );

            return $user->load(['role', 'company', 'employee']);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateAccess(User $managedUser, array $data, User $actor, Request $request): User
    {
        return DB::transaction(function () use ($managedUser, $data, $actor, $request): User {
            $this->assertManageableUser($actor, $managedUser);
            $this->assertAssignableCompany($actor, (int) $data['company_id']);

            $before = [
                'company_id' => $managedUser->company_id,
                'role_id' => $managedUser->role_id,
                'status' => $managedUser->status,
            ];

            $managedUser->forceFill([
                'company_id' => $data['company_id'],
                'role_id' => $data['role_id'],
                'status' => $data['status'],
            ])->save();

            $after = [
                'company_id' => $managedUser->company_id,
                'role_id' => $managedUser->role_id,
                'status' => $managedUser->status,
            ];

            $this->auditLogger->record(
                $actor,
                'admin.user.access_updated',
                'Updated user access for '.$managedUser->email,
                $managedUser,
                [
                    'before' => $before,
                    'after' => $after,
                ],
                $request,
            );

            return $managedUser->load(['role', 'company', 'employee']);
        });
    }

    private function assertAssignableCompany(User $actor, int $companyId): void
    {
        if ($actor->hasPermission('*')) {
            return;
        }

        if ($actor->company_id === null || $companyId !== (int) $actor->company_id) {
            throw ValidationException::withMessages([
                'company_id' => 'Company administrators can manage users only in their own company.',
            ]);
        }
    }

    private function assertManageableUser(User $actor, User $managedUser): void
    {
        if ($actor->hasPermission('*')) {
            return;
        }

        if ($actor->company_id === null || $managedUser->company_id === null || (int) $managedUser->company_id !== (int) $actor->company_id) {
            throw ValidationException::withMessages([
                'user' => 'The selected user is outside your administration scope.',
            ]);
        }
    }
}
