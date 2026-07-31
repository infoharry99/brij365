<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\PerformanceReview;
use App\Models\PerformanceScoreOverrideRequest;
use App\Services\Hr\PerformanceScoringWorkflowService;

final readonly class RequestPerformanceScoreOverride
{
    public function __construct(private PerformanceScoringWorkflowService $workflow) {}

    public function execute(PerformanceReview $review, HrCommandData $command): PerformanceScoreOverrideRequest
    {
        return $this->workflow->requestOverride(
            $review,
            $command->attributes,
            (int) $command->attributes['lock_version'],
            $command->actor,
            $command->request,
        );
    }
}
