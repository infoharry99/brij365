<?php

namespace App\Application\Procurement\Actions;

use App\Domain\Procurement\Services\ProcurementWorkspaceRegister;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListGoodsReceipts
{
    public function __construct(private readonly ProcurementWorkspaceRegister $register) {}

    /** @param array<string,mixed> $filters */
    public function execute(User $user, array $filters): LengthAwarePaginator
    {
        return $this->register->goodsReceipts($user, $filters);
    }
}
