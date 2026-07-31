<?php

namespace App\Application\Scoring\Actions;

use App\Application\Scoring\DTOs\UpdateScoringRuleData;
use App\Domain\Scoring\Services\ScoringConfigurationValidator;
use App\Domain\Scoring\Services\ScoringRuleDraftService;
use App\Models\ScoringRule;
use App\Models\User;
use Illuminate\Http\Request;

final class UpdateScoringRuleDraft
{
    public function __construct(
        private readonly ScoringConfigurationValidator $validator,
        private readonly ScoringRuleDraftService $service,
    ) {
    }

    public function handle(ScoringRule $rule, UpdateScoringRuleData $data, User $actor, Request $request): ScoringRule
    {
        $this->validator->validate($data->configuration);

        return $this->service->update($rule, $data, $actor, $request);
    }
}
