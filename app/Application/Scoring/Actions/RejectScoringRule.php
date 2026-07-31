<?php

namespace App\Application\Scoring\Actions;

use App\Domain\Scoring\Services\ScoringRuleLifecycleService;
use App\Models\ScoringRule;
use App\Models\User;
use Illuminate\Http\Request;

final class RejectScoringRule
{
    public function __construct(private readonly ScoringRuleLifecycleService $service) {}

    public function handle(ScoringRule $rule, string $reason, User $actor, Request $request): ScoringRule
    {
        return $this->service->reject($rule, $reason, $actor, $request);
    }
}
