<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\ExitInterviewSubmissionData;
use App\Application\Scoring\Actions\RefreshExitFeedbackScore;
use App\Models\EmployeeExitInterview;
use App\Models\User;
use App\Services\Hr\EmployeeExitInterviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class SubmitExitInterview
{
    public function __construct(
        private EmployeeExitInterviewService $exitInterviews,
        private RefreshExitFeedbackScore $refreshScore,
    ) {}

    public function execute(
        EmployeeExitInterview $interview,
        ExitInterviewSubmissionData $data,
        User $actor,
        Request $request,
    ): EmployeeExitInterview {
        return DB::transaction(function () use ($interview, $data, $actor, $request): EmployeeExitInterview {
            $submitted = $this->exitInterviews->submit($interview, $data->toArray(), $actor, $request);
            $this->refreshScore->executeWhenReady($submitted);

            return $submitted;
        });
    }
}
