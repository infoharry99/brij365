<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectTeamAssignment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationCenterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectManagementService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationCenterService $notifications,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, User $actor, ?Request $request = null): Project
    {
        return DB::transaction(function () use ($data, $actor, $request): Project {
            $project = Project::create([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'] ?? null,
                'code' => strtoupper((string) $data['code']),
                'name' => $data['name'],
                'project_type' => $data['project_type'],
                'city' => $data['city'],
                'state' => strtoupper((string) $data['state']),
                'status' => $data['status'],
                'budget_amount' => $data['budget_amount'] ?? 0,
                'target_roi_percent' => $data['target_roi_percent'] ?? 0,
                'starts_on' => $data['starts_on'] ?? null,
                'ends_on' => $data['ends_on'] ?? null,
            ]);

            $this->auditLogger->record(
                $actor,
                'projects.project.created',
                'Created project master record',
                $project,
                [
                    'code' => $project->code,
                    'company_id' => $project->company_id,
                    'branch_id' => $project->branch_id,
                    'status' => $project->status,
                    'budget_amount' => (float) $project->budget_amount,
                    'target_roi_percent' => (float) $project->target_roi_percent,
                ],
                $request,
            );

            $this->notifications->sendToUser($actor, [
                'category' => 'project_master',
                'severity' => 'success',
                'title' => 'Project master created',
                'body' => $project->code.' · '.$project->name.' has been added to Builder360.',
                'action_url' => route('builder360.dashboard', [], false).'#projects',
                'payload' => [
                    'project_id' => $project->id,
                    'project_code' => $project->code,
                    'company_id' => $project->company_id,
                ],
            ], $actor, $project);

            return $project->load(['company', 'branch']);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Project $project, array $data, User $actor, ?Request $request = null): Project
    {
        return DB::transaction(function () use ($project, $data, $actor, $request): Project {
            $lockedProject = Project::query()
                ->whereKey($project->id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = [
                'company_id' => $lockedProject->company_id,
                'branch_id' => $lockedProject->branch_id,
                'code' => $lockedProject->code,
                'name' => $lockedProject->name,
                'project_type' => $lockedProject->project_type,
                'city' => $lockedProject->city,
                'state' => $lockedProject->state,
                'status' => $lockedProject->status,
                'budget_amount' => (float) $lockedProject->budget_amount,
                'target_roi_percent' => (float) $lockedProject->target_roi_percent,
                'starts_on' => $lockedProject->starts_on?->toDateString(),
                'ends_on' => $lockedProject->ends_on?->toDateString(),
            ];

            $lockedProject->fill([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'] ?? null,
                'code' => strtoupper((string) $data['code']),
                'name' => $data['name'],
                'project_type' => $data['project_type'],
                'city' => $data['city'],
                'state' => strtoupper((string) $data['state']),
                'status' => $data['status'],
                'budget_amount' => $data['budget_amount'] ?? 0,
                'target_roi_percent' => $data['target_roi_percent'] ?? 0,
                'starts_on' => $data['starts_on'] ?? null,
                'ends_on' => $data['ends_on'] ?? null,
            ])->save();

            $after = [
                'company_id' => $lockedProject->company_id,
                'branch_id' => $lockedProject->branch_id,
                'code' => $lockedProject->code,
                'name' => $lockedProject->name,
                'project_type' => $lockedProject->project_type,
                'city' => $lockedProject->city,
                'state' => $lockedProject->state,
                'status' => $lockedProject->status,
                'budget_amount' => (float) $lockedProject->budget_amount,
                'target_roi_percent' => (float) $lockedProject->target_roi_percent,
                'starts_on' => $lockedProject->starts_on?->toDateString(),
                'ends_on' => $lockedProject->ends_on?->toDateString(),
            ];

            $this->auditLogger->record(
                $actor,
                'projects.project.updated',
                'Updated project master record',
                $lockedProject,
                [
                    'before' => $before,
                    'after' => $after,
                ],
                $request,
            );

            $this->notifications->sendToUser($actor, [
                'category' => 'project_master',
                'severity' => 'info',
                'title' => 'Project master updated',
                'body' => $lockedProject->code.' · '.$lockedProject->name.' has been updated.',
                'action_url' => route('builder360.dashboard', [], false).'#projects',
                'payload' => [
                    'project_id' => $lockedProject->id,
                    'project_code' => $lockedProject->code,
                    'company_id' => $lockedProject->company_id,
                ],
            ], $actor, $lockedProject);

            return $lockedProject->load(['company', 'branch']);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function assignTeamMember(Project $project, array $data, User $actor, ?Request $request = null): ProjectTeamAssignment
    {
        return DB::transaction(function () use ($project, $data, $actor, $request): ProjectTeamAssignment {
            $lockedProject = Project::query()
                ->whereKey($project->id)
                ->lockForUpdate()
                ->firstOrFail();

            $duplicate = ProjectTeamAssignment::query()
                ->where('project_id', $lockedProject->id)
                ->where('user_id', $data['user_id'])
                ->where('status', 'active')
                ->lockForUpdate()
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'user_id' => 'This user is already active on the selected project team.',
                ]);
            }

            $assignment = ProjectTeamAssignment::create([
                'company_id' => $lockedProject->company_id,
                'project_id' => $lockedProject->id,
                'user_id' => $data['user_id'],
                'employee_id' => $data['employee_id'] ?? null,
                'role_label' => $data['role_label'],
                'department' => $data['department'] ?? null,
                'access_level' => $data['access_level'],
                'status' => 'active',
                'starts_on' => $data['starts_on'] ?? null,
                'ends_on' => $data['ends_on'] ?? null,
                'notes' => $data['notes'] ?? null,
                'metadata' => [
                    'source' => 'project_master_team_tab',
                    'assigned_from_project_profile' => true,
                ],
                'assigned_by_user_id' => $actor->id,
            ]);

            $assignment->load(['project', 'user', 'employee', 'assignedBy']);

            $this->auditLogger->record(
                $actor,
                'projects.team_assignment.created',
                'Assigned project team member',
                $assignment,
                [
                    'project_id' => $lockedProject->id,
                    'project_code' => $lockedProject->code,
                    'company_id' => $lockedProject->company_id,
                    'assigned_user_id' => $assignment->user_id,
                    'employee_id' => $assignment->employee_id,
                    'role_label' => $assignment->role_label,
                    'department' => $assignment->department,
                    'access_level' => $assignment->access_level,
                    'starts_on' => $assignment->starts_on?->toDateString(),
                    'ends_on' => $assignment->ends_on?->toDateString(),
                ],
                $request,
            );

            $this->notifications->sendToUser($assignment->user, [
                'category' => 'project_team',
                'severity' => 'info',
                'title' => 'Project assignment added',
                'body' => 'You were assigned to '.$lockedProject->code.' · '.$lockedProject->name.' as '.$assignment->role_label.'.',
                'action_url' => route('builder360.dashboard', [], false).'#projects',
                'payload' => [
                    'project_id' => $lockedProject->id,
                    'project_code' => $lockedProject->code,
                    'assignment_id' => $assignment->id,
                    'access_level' => $assignment->access_level,
                ],
            ], $actor, $assignment);

            return $assignment;
        });
    }

    public function revokeTeamMember(ProjectTeamAssignment $assignment, User $actor, ?Request $request = null): ProjectTeamAssignment
    {
        return DB::transaction(function () use ($assignment, $actor, $request): ProjectTeamAssignment {
            $lockedAssignment = ProjectTeamAssignment::query()
                ->with(['project', 'user', 'employee', 'assignedBy'])
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAssignment->status !== 'active') {
                throw ValidationException::withMessages([
                    'assignment' => 'Only active project team assignments can be revoked.',
                ]);
            }

            $lockedAssignment->forceFill([
                'status' => 'revoked',
                'revoked_by_user_id' => $actor->id,
                'revoked_at' => now(),
            ])->save();

            $lockedAssignment->load(['project', 'user', 'employee', 'assignedBy', 'revokedBy']);

            $this->auditLogger->record(
                $actor,
                'projects.team_assignment.revoked',
                'Revoked project team member assignment',
                $lockedAssignment,
                [
                    'project_id' => $lockedAssignment->project_id,
                    'project_code' => $lockedAssignment->project?->code,
                    'company_id' => $lockedAssignment->company_id,
                    'assigned_user_id' => $lockedAssignment->user_id,
                    'employee_id' => $lockedAssignment->employee_id,
                    'role_label' => $lockedAssignment->role_label,
                    'access_level' => $lockedAssignment->access_level,
                ],
                $request,
            );

            $this->notifications->sendToUser($lockedAssignment->user, [
                'category' => 'project_team',
                'severity' => 'warning',
                'title' => 'Project assignment revoked',
                'body' => 'Your assignment to '.$lockedAssignment->project?->code.' · '.$lockedAssignment->project?->name.' was revoked.',
                'action_url' => route('builder360.dashboard', [], false).'#projects',
                'payload' => [
                    'project_id' => $lockedAssignment->project_id,
                    'project_code' => $lockedAssignment->project?->code,
                    'assignment_id' => $lockedAssignment->id,
                ],
            ], $actor, $lockedAssignment);

            return $lockedAssignment;
        });
    }
}
