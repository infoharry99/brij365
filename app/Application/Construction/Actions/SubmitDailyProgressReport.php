<?php

namespace App\Application\Construction\Actions;

use App\Application\Construction\Data\ConstructionCommandData;
use App\Models\DailyProgressReport;
use App\Services\Construction\ConstructionService;

final class SubmitDailyProgressReport
{
    public function __construct(private readonly ConstructionService $service) {}

    public function execute(ConstructionCommandData $command): DailyProgressReport
    {
        return $this->service->submitDailyReport($command->attributes, $command->actor, $command->request);
    }
}
