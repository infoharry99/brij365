<?php

namespace App\Services\Collaboration;

use App\Models\SystemSetting;
use App\Models\User;

class ChatAccessService
{
    public const SETTING_KEY = 'chat_connect.role_access';

    /**
     * @return array<string, array<string, bool>>
     */
    public function roleAccessMatrix(?int $companyId = null): array
    {
        $setting = SystemSetting::query()
            ->where('setting_key', self::SETTING_KEY)
            ->where('status', 'active')
            ->when($companyId, fn ($query) => $query->where(function ($query) use ($companyId): void {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            }))
            ->latest('version')
            ->first();

        $value = is_array($setting?->value) ? $setting->value : [];
        $roles = is_array($value['roles'] ?? null) ? $value['roles'] : [];

        return array_replace_recursive($this->defaultRoleAccess(), $roles);
    }

    /**
     * @return array<string, bool>
     */
    public function capabilitiesFor(User $user): array
    {
        if ($user->status !== 'active') {
            return $this->disabledCapabilities();
        }

        $roleSlug = $user->role?->slug ?: $this->slugFromRoleName($user->role?->name);
        
        $matrix = $this->roleAccessMatrix($user->company_id);

        $capabilities = $matrix[$roleSlug] ?? null;

        if (! is_array($capabilities)) {
            $capabilities = $this->disabledCapabilities();
        }

        return array_replace($this->disabledCapabilities(), $capabilities);
    }

    public function can(User $user, string $capability): bool
    {
        return (bool) ($this->capabilitiesFor($user)[$capability] ?? false);
    }

    public function canView(User $user): bool
    {
        return $this->can($user, 'can_view');
    }

    public function isReadOnly(User $user): bool
    {
        return (bool) ($this->capabilitiesFor($user)['read_only'] ?? false);
    }

    /**
     * @return array<string, array<string, bool>>
     */
    
    public function defaultRoleAccess(): array
    {
        $fullAccess = [
            'can_view'            => true,
            'can_post'            => true,
            'can_create_dm'       => true,
            'can_create_group'    => true,
            'can_create_channel'  => true,
            'can_upload'          => true,
            'can_send_voice'      => true,
            'can_create_poll'     => true,
            'can_vote_poll'       => true,
            'can_manage_members'  => true,
            'can_archive'         => true,
            'can_export'          => true,
            'read_only'           => false,
        ];
    
        $standardAccess = [
            'can_view'            => true,
            'can_post'            => true,
            'can_create_dm'       => true,
            'can_create_group'    => true,
            'can_create_channel'  => false,
            'can_upload'          => true,
            'can_send_voice'      => false,
            'can_create_poll'     => false,
            'can_vote_poll'       => true,
            'can_manage_members'  => false,
            'can_archive'         => false,
            'can_export'          => false,
            'read_only'           => false,
        ];
    
        return [
            // Full access roles
            'director'             => $fullAccess,
            'system_admin'         => $fullAccess,
    
            // Standard internal roles
            'sales_head'           => $standardAccess,
            'construction_head'    => $standardAccess,
            'finance_head'         => $standardAccess,
            'hr_manager'           => $standardAccess,
            'payroll'              => $standardAccess,
            'recruiter'            => $standardAccess,
            'auditor'              => $standardAccess,
            'compliance'           => $standardAccess,
            'employee'             => $standardAccess,
    
            // External — no chat access
            'channel_partner'          => $this->disabledCapabilities(),
            'executive_partner_broker' => $this->disabledCapabilities(),
            'buyer'                    => $this->disabledCapabilities(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function disabledCapabilities(): array
    {
        return [
            'can_view' => false,
            'can_create_dm' => false,
            'can_create_group' => false,
            'can_create_channel' => false,
            'can_post' => false,
            'can_upload' => false,
            'can_send_voice' => false,
            'can_create_poll' => false,
            'can_vote_poll' => false,
            'can_manage_members' => false,
            'can_archive' => false,
            'can_export' => false,
            'read_only' => true,
        ];
    }

    private function slugFromRoleName(?string $roleName): string
    {
        return str($roleName ?: '')->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    }
}
