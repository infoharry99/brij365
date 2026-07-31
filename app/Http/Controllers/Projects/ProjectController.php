<?php

namespace App\Http\Controllers\Projects;

use App\Application\Projects\Actions\AssignProjectTeamMember;
use App\Application\Projects\Actions\CreateProject;
use App\Application\Projects\Actions\ExportProjectCostRoi;
use App\Application\Projects\Actions\ListProjectWorkspace;
use App\Application\Projects\Actions\RevokeProjectTeamMember;
use App\Application\Projects\Actions\UpdateProject;
use App\Application\Projects\Data\ProjectCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ProjectCostRoiExportRequest;
use App\Http\Requests\Projects\ProjectIndexRequest;
use App\Http\Requests\Projects\RevokeProjectTeamAssignmentRequest;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\StoreProjectTeamAssignmentRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\ProjectTeamAssignmentResource;
use App\Models\Project;
use App\Models\ProjectTeamAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ProjectController extends Controller
{
    public function index(
        ProjectIndexRequest $request,
        ListProjectWorkspace $action,
    ): AnonymousResourceCollection|View {
        $page = $action->execute($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return ProjectResource::collection($page->projects);
        }

        return view('projects.index', [
            'projects' => $page->projects->withQueryString(), 'filters' => $page->filters,
            'companies' => $page->companies, 'branches' => $page->branches,
            'assignableUsers' => $page->assignableUsers, 'employees' => $page->employees,
            'statuses' => $page->statuses, 'projectTypes' => $page->projectTypes, 'accessLevels' => $page->accessLevels,
            'canCreateProject' => $page->canCreate, 'canManageProjectTeam' => $page->canManageTeam,
            'projectHealthScores' => $page->healthScores,
        ]);
    }

    public function exportCostRoi(
        ProjectCostRoiExportRequest $request,
        ExportProjectCostRoi $action,
    ): Response {
        $filters = $request->validated();
        unset($filters['format']);
        $export = $action->execute($request->user(), $filters, $request);

        return response($export->content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$export->filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(StoreProjectRequest $request, CreateProject $action): ProjectResource|RedirectResponse
    {
        $project = $action->execute(new ProjectCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('projects.index')
                ->with('status', "Project {$project->code} created.");
        }

        return (new ProjectResource($project))->additional(['message' => 'Project master created.']);
    }

    public function update(UpdateProjectRequest $request, Project $project, UpdateProject $action): ProjectResource|RedirectResponse
    {
        $project = $action->execute($project, new ProjectCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('projects.index')
                ->with('status', "Project {$project->code} updated.");
        }

        return (new ProjectResource($project))->additional(['message' => 'Project master updated.']);
    }

    public function storeTeamAssignment(
        StoreProjectTeamAssignmentRequest $request,
        Project $project,
        AssignProjectTeamMember $action,
    ): ProjectTeamAssignmentResource|RedirectResponse {
        $assignment = $action->execute($project, new ProjectCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('projects.index')
                ->with('status', "{$assignment->user?->name} assigned to {$project->code}.");
        }

        return (new ProjectTeamAssignmentResource($assignment))->additional(['message' => 'Project team member assigned.']);
    }

    public function destroyTeamAssignment(
        RevokeProjectTeamAssignmentRequest $request,
        Project $project,
        ProjectTeamAssignment $projectTeamAssignment,
        RevokeProjectTeamMember $action,
    ): ProjectTeamAssignmentResource|RedirectResponse {
        $assignment = $action->execute($projectTeamAssignment, new ProjectCommandData([], $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('projects.index')
                ->with('status', "Project assignment for {$assignment->user?->name} revoked.");
        }

        return (new ProjectTeamAssignmentResource($assignment))->additional(['message' => 'Project team member assignment revoked.']);
    }
}
