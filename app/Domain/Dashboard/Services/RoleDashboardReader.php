<?php

namespace App\Domain\Dashboard\Services;

use App\Application\Dashboard\Data\DashboardContextData;
use App\Application\Dashboard\Data\DashboardPageData;
use App\Models\User;
use App\Services\Builder360\Builder360Bootstrap;

final class RoleDashboardReader
{
    public function __construct(private readonly Builder360Bootstrap $dashboardSource) {}

    public function read(User $actor, DashboardContextData $context): DashboardPageData
    {
        $payload = $this->dashboardSource->dashboardForRoleContext(
            $actor,
            $context->roleSlug,
            $context->projectId,
            $context->period,
        );

        return new DashboardPageData(
            dashboard: $payload['role_dashboard'] ?? [],
            navigationContext: [
                'active_role_context' => $payload['active_role_context'] ?? [],
                'active_project_context' => $payload['active_project_context'] ?? [],
                'active_dashboard_period' => $payload['active_dashboard_period'] ?? [],
                'buyer_portal' => $payload['buyer_portal'] ?? null,
                'partner_portal' => $payload['partner_portal'] ?? null,
            ],
        );
    }
}
