<?php

namespace App\Application\Recruitment\Actions;

use App\Application\Recruitment\Data\InterviewFeedbackData;
use App\Application\Scoring\Actions\RefreshCurrentScore;
use App\Models\Interview;
use App\Models\User;
use App\Services\Recruitment\RecruitmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class SubmitInterviewPanelFeedback
{
    public function __construct(
        private RecruitmentService $recruitment,
        private RefreshCurrentScore $refreshScore,
    ) {}

    public function execute(
        Interview $interview,
        InterviewFeedbackData $data,
        User $actor,
        Request $request,
    ): Interview {
        return DB::transaction(function () use ($interview, $data, $actor, $request): Interview {
            $updated = $this->recruitment->submitInterviewFeedback($interview, $data->toArray(), $actor, $request);
            $this->refreshScore->executeWhenReady('recruitment_interview', $updated);

            return $updated;
        });
    }
}
