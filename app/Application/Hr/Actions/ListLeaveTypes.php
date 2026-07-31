<?php

namespace App\Application\Hr\Actions;

use App\Domain\Hr\Services\LeaveWorkspaceRegister;
use App\Models\User;

final class ListLeaveTypes
{
    public function __construct(private readonly LeaveWorkspaceRegister $r) {}

    public function execute(User $u, array $f): mixed
    {
        return $this->r->types($u, $f);
    }
}
