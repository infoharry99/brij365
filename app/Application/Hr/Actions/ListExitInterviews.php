<?php

namespace App\Application\Hr\Actions;

use App\Domain\Hr\Services\ExitInterviewRegister;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListExitInterviews
{
    public function __construct(private readonly ExitInterviewRegister $register) {}

    public function execute(User $actor, array $filters): LengthAwarePaginator
    {
        return $this->register->all($actor, $filters);
    }
}
