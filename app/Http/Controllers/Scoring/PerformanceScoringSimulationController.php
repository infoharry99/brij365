<?php

namespace App\Http\Controllers\Scoring;

use App\Application\Scoring\Actions\SimulatePerformanceScore;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scoring\SimulatePerformanceScoreRequest;
use App\Models\ScoringRule;
use Illuminate\Http\RedirectResponse;

final class PerformanceScoringSimulationController extends Controller
{
    public function __invoke(
        ScoringRule $scoringRule,
        SimulatePerformanceScoreRequest $request,
        SimulatePerformanceScore $simulate,
    ): RedirectResponse {
        $result = $simulate->execute($scoringRule, $request->simulationInput());

        return redirect()->route('scoring.index', ['view' => 'simulation'])
            ->with('status', 'Non-authoritative performance simulation completed. No review or score snapshot was created or changed.')
            ->with('performance_simulation', $result->toView());
    }
}
