<?php

namespace App\Application\Projects\Actions;

use App\Application\Projects\Data\ProjectCommandData;
use App\Models\Project;
use App\Models\ProjectTeamAssignment;
use App\Services\Projects\ProjectManagementService;

final class AssignProjectTeamMember
{
    public function __construct(private readonly ProjectManagementService $projects) {}

    public function execute(Project $project, ProjectCommandData $command): ProjectTeamAssignment
    {
        return $this->projects->assignTeamMember($project, $command->attributes, $command->actor, $command->request);
    }
}
