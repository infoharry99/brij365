<?php

namespace App\Application\Scoring\Actions;

use App\Domain\Scoring\Services\ScoreOverrideService;
use App\Models\ScoreSnapshot;
use App\Models\User;
use Illuminate\Http\Request;

final class OverrideScoreSnapshot
{
    public function __construct(private readonly ScoreOverrideService $service) {}
    public function handle(ScoreSnapshot $snapshot, float $score, string $reason, User $actor, Request $request): ScoreSnapshot
    {
        return $this->service->override($snapshot, $score, $reason, $actor, $request);
    }
}
