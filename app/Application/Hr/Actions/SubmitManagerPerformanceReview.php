<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\ManagerPerformanceReviewData;
use App\Application\Scoring\Actions\RefreshCurrentScore;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Services\Hr\PerformanceManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class SubmitManagerPerformanceReview
{
    public function __construct(
        private PerformanceManagementService $performance,
        private RefreshCurrentScore $refreshScore,
    ) {}

    public function execute(
        PerformanceReview $review,
        ManagerPerformanceReviewData $data,
        User $actor,
        Request $request,
    ): PerformanceReview {
        return DB::transaction(function () use ($review, $data, $actor, $request): PerformanceReview {
            $updated = $this->performance->submitManagerReview($review, $data->toArray(), $actor, $request);
            $this->refreshScore->executeWhenReady('employee_performance', $updated);

            return $updated;
        });
    }
}
