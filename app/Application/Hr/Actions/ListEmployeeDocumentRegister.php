<?php

namespace App\Application\Hr\Actions;

use App\Domain\Hr\Services\EmployeeDocumentRegister;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListEmployeeDocumentRegister
{
    public function __construct(private readonly EmployeeDocumentRegister $register) {}

    public function execute(User $actor, array $filters): LengthAwarePaginator
    {
        return $this->register->companyDocuments($actor, $filters);
    }
}
