<?php

namespace App\Application\Projects\Actions;

use App\Application\Projects\Data\ProjectCommandData;
use App\Models\Project;
use App\Services\Projects\ProjectManagementService;

final class CreateProject
{
    public function __construct(private readonly ProjectManagementService $projects) {}

    public function execute(ProjectCommandData $command): Project
    {
        return $this->projects->create($command->attributes, $command->actor, $command->request);
    }
}
