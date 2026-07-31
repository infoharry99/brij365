<?php

namespace App\Http\Controllers\Scoring;

use App\Application\Scoring\Actions\ShowScoringOverview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scoring\ScoringRuleIndexRequest;
use Illuminate\View\View;

final class ScoringOverviewController extends Controller
{
    public function __invoke(ScoringRuleIndexRequest $request, ShowScoringOverview $show): View
    {
        return view('scoring.index', [
            'page' => $show->handle($request->user(), $request->validated()),
            'statutorySimulation' => $request->session()->get('statutory_simulation'),
            'performanceSimulation' => $request->session()->get('performance_simulation'),
            'rosterSimulation' => $request->session()->get('roster_simulation'),
        ]);
    }
}
