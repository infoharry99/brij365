<?php

namespace App\Application\Projects\Actions;

use App\Application\Projects\Data\ProjectCommandData;
use App\Models\ProjectTeamAssignment;
use App\Services\Projects\ProjectManagementService;

final class RevokeProjectTeamMember
{
    public function __construct(private readonly ProjectManagementService $projects) {}

    public function execute(ProjectTeamAssignment $assignment, ProjectCommandData $command): ProjectTeamAssignment
    {
        return $this->projects->revokeTeamMember($assignment, $command->actor, $command->request);
    }
}
