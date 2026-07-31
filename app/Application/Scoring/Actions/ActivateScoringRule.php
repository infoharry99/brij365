<?php
namespace App\Application\Scoring\Actions;
use App\Domain\Scoring\Services\ScoringRuleLifecycleService;
use App\Domain\Scoring\Services\ScoringRecalculationService;
use App\Models\ScoringRule;
use App\Models\User;
use Illuminate\Http\Request;
final class ActivateScoringRule {
    public function __construct(private readonly ScoringRuleLifecycleService $service, private readonly ScoringRecalculationService $recalculation) {}
    public function handle(ScoringRule $rule, User $actor, Request $request): ScoringRule
    {
        $activated = $this->service->activate($rule, $actor, $request);
        if ($activated->status === 'active') {
            $this->recalculation->start($activated, $actor, $request);
        }
        return $activated;
    }
}
