<?php

namespace App\Application\Scoring\Actions;

use App\Application\Scoring\DTOs\CreateScoringRuleData;
use App\Domain\Scoring\Services\ScoringRuleDraftService;
use App\Models\ScoringRule;
use App\Models\User;
use Illuminate\Http\Request;

final class CreateScoringRuleDraft
{
    public function __construct(private readonly ScoringRuleDraftService $service)
    {
    }

    public function handle(CreateScoringRuleData $data, User $actor, Request $request): ScoringRule
    {
        return $this->service->create($data, $actor, $request);
    }
}
