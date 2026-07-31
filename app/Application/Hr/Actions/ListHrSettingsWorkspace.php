<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrSettingsWorkspaceData;
use App\Domain\Hr\Services\HrSettingsRegister;
use App\Models\SystemSetting;
use App\Models\User;

final class ListHrSettingsWorkspace
{
    public function __construct(private readonly HrSettingsRegister $register) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function execute(User $actor, array $filters): HrSettingsWorkspaceData
    {
        $filters['tab'] = $filters['tab'] ?? 'overview';

        return new HrSettingsWorkspaceData(
            settings: $this->register->settings($actor, $filters),
            filters: $filters,
            summary: $this->register->summary($actor, $filters),
            tabs: [
                'overview' => 'Governed register',
                'hr' => 'HR rules',
                'payroll' => 'Payroll rules',
                'workflow' => 'Approval workflows',
            ],
            canManage: $actor->can('create', SystemSetting::class),
            canApprove: $actor->hasPermission('*') || $actor->hasPermission('settings.approve'),
            canViewRoles: $actor->hasPermission('*') || $actor->hasPermission('roles.view'),
        );
    }
}
