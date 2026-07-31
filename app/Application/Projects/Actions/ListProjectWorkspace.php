<?php

namespace App\Application\Projects\Actions;

use App\Application\Projects\Data\ProjectWorkspaceData;
use App\Domain\Projects\Services\ProjectWorkspaceRegister;
use App\Models\Project;
use App\Models\ProjectTeamAssignment;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

final class ListProjectWorkspace
{
    public function __construct(
        private readonly ProjectWorkspaceRegister $register,
        private readonly CompanyScopeService $companyScope,
        private readonly ReadProjectHealthScores $healthScores,
    ) {}

    /** @param array<string, mixed> $filters */
    public function execute(User $user, array $filters): ProjectWorkspaceData
    {
        $projects = $this->register->projects($user, $filters);
        $companyId = $this->companyScope->companyIdFor($user);

        return new ProjectWorkspaceData(
            projects: $projects,
            filters: $filters,
            companies: $this->register->companies($user),
            branches: $this->register->branches($user),
            assignableUsers: $this->register->users($user),
            employees: $this->register->employees($user),
            statuses: ['planned' => 'Planned', 'active' => 'Active', 'on_hold' => 'On Hold', 'completed' => 'Completed', 'archived' => 'Archived'],
            projectTypes: ['residential' => 'Residential', 'commercial' => 'Commercial', 'villa' => 'Villa', 'mixed_use' => 'Mixed Use', 'plotted' => 'Plotted', 'redevelopment' => 'Redevelopment'],
            accessLevels: ['read' => 'Read', 'contribute' => 'Contribute', 'manage' => 'Manage', 'approve' => 'Approve'],
            canCreate: $user->can('create', Project::class),
            canManageTeam: $user->can('create', ProjectTeamAssignment::class),
            healthScores: $companyId === null ? [] : $this->healthScores->execute($companyId, $projects->getCollection()->modelKeys()),
        );
    }
}
