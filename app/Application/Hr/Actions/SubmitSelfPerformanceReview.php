<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\PerformanceReview;
use App\Services\Hr\PerformanceManagementService;

final class SubmitSelfPerformanceReview
{
    public function __construct(private readonly PerformanceManagementService $service) {}

    public function execute(PerformanceReview $review, HrCommandData $command): PerformanceReview
    {
        return $this->service->submitSelfReview($review, $command->attributes, $command->actor, $command->request);
    }
}
