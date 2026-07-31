<?php

namespace App\Application\Construction\Actions;

use App\Domain\Construction\Services\ConstructionWorkspaceRegister;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListContractorMeasurements
{
    public function __construct(private readonly ConstructionWorkspaceRegister $register) {}

    /** @param array<string, mixed> $filters */
    public function execute(User $user, array $filters): LengthAwarePaginator
    {
        return $this->register->measurements($user, $filters);
    }
}
