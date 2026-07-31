<?php

namespace App\Application\Projects\Actions;

use App\Application\Projects\Data\ProjectCommandData;
use App\Models\Project;
use App\Services\Projects\ProjectManagementService;

final class UpdateProject
{
    public function __construct(private readonly ProjectManagementService $projects) {}

    public function execute(Project $project, ProjectCommandData $command): Project
    {
        return $this->projects->update($project, $command->attributes, $command->actor, $command->request);
    }
}
