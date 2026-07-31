<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\EmployeeExitInterview;
use App\Services\Hr\EmployeeExitInterviewService;

final class ScheduleExitInterview
{
    public function __construct(private readonly EmployeeExitInterviewService $service) {}

    public function execute(HrCommandData $command): EmployeeExitInterview
    {
        return $this->service->schedule($command->attributes, $command->actor, $command->request);
    }
}
