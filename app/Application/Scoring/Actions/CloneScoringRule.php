<?php

namespace App\Application\Scoring\Actions;

use App\Domain\Scoring\Services\ScoringRuleDraftService;
use App\Models\ScoringRule;
use App\Models\User;
use Illuminate\Http\Request;

final class CloneScoringRule
{
    public function __construct(private readonly ScoringRuleDraftService $service) {}

    public function handle(ScoringRule $source, string $reason, User $actor, Request $request, bool $rollback = false): ScoringRule
    {
        return $this->service->clone($source, $reason, $actor, $request, $rollback);
    }
}
