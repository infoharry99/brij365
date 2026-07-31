<?php

namespace App\Application\Hr\Actions;

use App\Domain\Hr\Services\SeparationSettlementRegister;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListSeparationSettlements
{
    public function __construct(private readonly SeparationSettlementRegister $register) {}

    public function execute(User $actor, array $filters): LengthAwarePaginator
    {
        return $this->register->all($actor, $filters);
    }
}
