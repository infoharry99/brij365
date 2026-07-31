<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\PerformanceCycle;
use App\Services\Hr\PerformanceManagementService;

final class CreatePerformanceCycle
{
    public function __construct(private readonly PerformanceManagementService $service) {}

    public function execute(HrCommandData $command): PerformanceCycle
    {
        return $this->service->createCycle($command->attributes, $command->actor, $command->request);
    }
}
