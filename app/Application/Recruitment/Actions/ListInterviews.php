<?php

namespace App\Application\Recruitment\Actions;

use App\Domain\Recruitment\Services\RecruitmentWorkspaceRegister;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListInterviews
{
    public function __construct(private readonly RecruitmentWorkspaceRegister $register) {}

    public function execute(User $u, array $f): LengthAwarePaginator
    {
        return $this->register->interviews($u, $f);
    }
}
