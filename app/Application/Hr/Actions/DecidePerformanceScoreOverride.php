<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\PerformanceReview;
use App\Models\PerformanceScoreOverrideRequest;
use App\Services\Hr\PerformanceScoringWorkflowService;

final readonly class DecidePerformanceScoreOverride
{
    public function __construct(private PerformanceScoringWorkflowService $workflow) {}

    public function execute(PerformanceScoreOverrideRequest $override, bool $approve, HrCommandData $command): PerformanceReview
    {
        return $this->workflow->decide(
            $override,
            $approve,
            (string) $command->attributes['decision_reason'],
            (int) $command->attributes['lock_version'],
            $command->actor,
            $command->request,
        );
    }
}
