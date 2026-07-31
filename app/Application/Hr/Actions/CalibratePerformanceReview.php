<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\PerformanceReview;
use App\Services\Hr\PerformanceScoringWorkflowService;

final readonly class CalibratePerformanceReview
{
    public function __construct(private PerformanceScoringWorkflowService $workflow) {}

    public function execute(PerformanceReview $review, HrCommandData $command): PerformanceReview
    {
        return $this->workflow->calibrate(
            $review,
            (float) $command->attributes['hr_calibration'],
            (string) $command->attributes['hr_comments'],
            (int) $command->attributes['lock_version'],
            $command->actor,
            $command->request,
        );
    }
}
