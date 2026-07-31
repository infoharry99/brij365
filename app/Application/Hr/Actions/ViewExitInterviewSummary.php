<?php

namespace App\Application\Hr\Actions;

use App\Models\User;
use App\Services\Hr\EmployeeExitInterviewService;

final class ViewExitInterviewSummary
{
    public function __construct(private readonly EmployeeExitInterviewService $service) {}

    public function execute(User $actor, array $filters): array
    {
        return $this->service->summary($filters, $actor);
    }
}
