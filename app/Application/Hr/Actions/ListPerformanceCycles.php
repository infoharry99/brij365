<?php

namespace App\Application\Hr\Actions;

use App\Domain\Hr\Services\PerformanceRegister;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListPerformanceCycles
{
    public function __construct(private readonly PerformanceRegister $register) {}

    public function execute(User $actor, array $filters): LengthAwarePaginator
    {
        return $this->register->cycles($actor, $filters);
    }
}
