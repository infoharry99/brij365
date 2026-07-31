<?php

namespace App\Http\Controllers\Scoring;

use App\Application\Scoring\Actions\OverrideScoreSnapshot;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scoring\OverrideScoreSnapshotRequest;
use App\Models\ScoreSnapshot;
use Illuminate\Http\RedirectResponse;

final class ScoringSnapshotController extends Controller
{
    public function override(OverrideScoreSnapshotRequest $request, ScoreSnapshot $scoreSnapshot, OverrideScoreSnapshot $override): RedirectResponse
    {
        $snapshot = $override->handle($scoreSnapshot, (float) $request->validated('score'), $request->validated('reason'), $request->user(), $request);

        return back()->with('status', "Score overridden to {$snapshot->total_score} with retained calculation evidence.");
    }
}
