<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\EmployeeConfirmationCase;
use App\Services\Hr\EmployeeConfirmationService;

final class CreateConfirmationCase
{
    public function __construct(private readonly EmployeeConfirmationService $service) {}

    public function execute(HrCommandData $command): EmployeeConfirmationCase
    {
        return $this->service->createCase($command->attributes, $command->actor, $command->request);
    }
}
