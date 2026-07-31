<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\PerformanceReview;
use App\Services\Hr\PerformanceManagementService;

final class ClosePerformanceReview
{
    public function __construct(private readonly PerformanceManagementService $service) {}

    public function execute(PerformanceReview $review, HrCommandData $command): PerformanceReview
    {
        return $this->service->closeReview($review, $command->attributes, $command->actor, $command->request);
    }
}
