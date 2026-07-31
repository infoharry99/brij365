<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\ConfirmationRecommendationData;
use App\Application\Scoring\Actions\RefreshCurrentScore;
use App\Models\EmployeeConfirmationCase;
use App\Models\User;
use App\Services\Hr\EmployeeConfirmationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class SubmitConfirmationRecommendation
{
    public function __construct(
        private EmployeeConfirmationService $confirmations,
        private RefreshCurrentScore $refreshScore,
    ) {}

    public function execute(
        EmployeeConfirmationCase $case,
        ConfirmationRecommendationData $data,
        User $actor,
        Request $request,
    ): EmployeeConfirmationCase {
        return DB::transaction(function () use ($case, $data, $actor, $request): EmployeeConfirmationCase {
            $updated = $this->confirmations->recommend($case, $data->toArray(), $actor, $request);
            $this->refreshScore->executeWhenReady('employee_confirmation', $updated);

            return $updated;
        });
    }
}
