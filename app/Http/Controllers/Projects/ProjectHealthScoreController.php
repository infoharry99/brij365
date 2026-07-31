<?php

namespace App\Http\Controllers\Projects;

use App\Application\Projects\Actions\UpdateProjectHealthEvidence;
use App\Application\Projects\Data\ProjectHealthEvidenceData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\UpdateProjectHealthEvidenceRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;

final class ProjectHealthScoreController extends Controller
{
    public function update(
        UpdateProjectHealthEvidenceRequest $request,
        Project $project,
        UpdateProjectHealthEvidence $action,
    ): RedirectResponse {
        $snapshot = $action->execute(
            $project,
            ProjectHealthEvidenceData::from($request->validated()),
            $request->user(),
            $request,
        );

        return redirect()->route('projects.index')->with(
            'status',
            "Project health score updated to ".number_format((float) $snapshot->total_score, 2).'.',
        );
    }
}
