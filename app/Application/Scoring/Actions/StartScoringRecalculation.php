<?php

namespace App\Application\Scoring\Actions;

use App\Domain\Scoring\Services\ScoringRecalculationService;
use App\Models\ScoringRecalculationRun;
use App\Models\ScoringRule;
use App\Models\User;
use Illuminate\Http\Request;

final class StartScoringRecalculation
{
    public function __construct(private readonly ScoringRecalculationService $service) {}
    public function handle(ScoringRule $rule, User $actor, Request $request): ScoringRecalculationRun
    {
        return $this->service->start($rule, $actor, $request);
    }
}
