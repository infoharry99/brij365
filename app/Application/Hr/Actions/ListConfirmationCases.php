<?php

namespace App\Application\Hr\Actions;

use App\Domain\Hr\Services\ConfirmationCaseRegister;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListConfirmationCases
{
    public function __construct(private readonly ConfirmationCaseRegister $register) {}

    public function execute(User $actor, array $filters): LengthAwarePaginator
    {
        return $this->register->cases($actor, $filters);
    }
}
