<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\EmployeeExitInterview;
use App\Services\Hr\EmployeeExitInterviewService;

final class ReviewExitInterview
{
    public function __construct(private readonly EmployeeExitInterviewService $service) {}

    public function execute(EmployeeExitInterview $interview, HrCommandData $command): EmployeeExitInterview
    {
        return $this->service->review($interview, $command->attributes, $command->actor, $command->request);
    }
}
