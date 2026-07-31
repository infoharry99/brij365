<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\EmployeeConfirmationCase;
use App\Services\Hr\EmployeeConfirmationService;

final class DecideConfirmationCase
{
    public function __construct(private readonly EmployeeConfirmationService $service) {}

    public function execute(EmployeeConfirmationCase $case, HrCommandData $command): EmployeeConfirmationCase
    {
        return $this->service->decide($case, $command->attributes, $command->actor, $command->request);
    }
}
