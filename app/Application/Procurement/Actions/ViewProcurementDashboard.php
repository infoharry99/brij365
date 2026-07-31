<?php

namespace App\Application\Procurement\Actions;

use App\Domain\Procurement\Services\ProcurementWorkspaceRegister;
use App\Models\User;

final class ViewProcurementDashboard
{
    public function __construct(private readonly ProcurementWorkspaceRegister $register) {}

    /** @param array<string,mixed> $filters */
    public function execute(User $user, array $filters): array
    {
        return $this->register->dashboard($user, $filters);
    }
}
