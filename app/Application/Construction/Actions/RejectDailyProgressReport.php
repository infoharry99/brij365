<?php

namespace App\Application\Construction\Actions;

use App\Application\Construction\Data\ConstructionCommandData;
use App\Models\DailyProgressReport;
use App\Services\Construction\ConstructionService;

final class RejectDailyProgressReport
{
    public function __construct(private readonly ConstructionService $service) {}

    public function execute(DailyProgressReport $report, ConstructionCommandData $command): DailyProgressReport
    {
        return $this->service->rejectDailyReport($report, (string) $command->attributes['reason'], $command->actor, $command->request);
    }
}
