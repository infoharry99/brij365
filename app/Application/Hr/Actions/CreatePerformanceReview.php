<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\PerformanceReview;
use App\Services\Hr\PerformanceManagementService;

final class CreatePerformanceReview
{
    public function __construct(private readonly PerformanceManagementService $service) {}

    public function execute(HrCommandData $command): PerformanceReview
    {
        return $this->service->createReview($command->attributes, $command->actor, $command->request);
    }
}
