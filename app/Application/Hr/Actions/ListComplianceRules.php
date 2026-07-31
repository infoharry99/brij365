<?php

namespace App\Application\Hr\Actions;

use App\Domain\Hr\Services\ComplianceRuleRegister;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListComplianceRules
{
    public function __construct(private readonly ComplianceRuleRegister $register) {}

    public function execute(User $actor, array $filters): LengthAwarePaginator
    {
        return $this->register->all($actor, $filters);
    }
}
