<?php

namespace App\Application\Dashboard\Actions;

use App\Application\Dashboard\Data\DashboardContextData;
use App\Application\Dashboard\Data\DashboardPageData;
use App\Domain\Dashboard\Services\RoleDashboardReader;
use App\Models\User;

final class ShowRoleDashboard
{
    public function __construct(private readonly RoleDashboardReader $dashboards) {}

    public function execute(User $actor, DashboardContextData $context): DashboardPageData
    {
        return $this->dashboards->read($actor, $context);
    }
}
