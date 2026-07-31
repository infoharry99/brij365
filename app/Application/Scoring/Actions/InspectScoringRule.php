<?php

namespace App\Application\Scoring\Actions;

use App\Application\Scoring\DTOs\ScoringRuleInspectionPageData;
use App\Domain\Scoring\Services\ScoringRuleInspectionService;
use App\Models\ScoringRule;
use App\Models\User;

final class InspectScoringRule
{
    public function __construct(private readonly ScoringRuleInspectionService $service) {}

    public function handle(ScoringRule $rule, User $user, ?int $compareTo = null): ScoringRuleInspectionPageData
    {
        return $this->service->inspect($rule, $user, $compareTo);
    }
}
