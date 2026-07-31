<?php
namespace App\Application\Finance\Actions;
use App\Application\Finance\Data\FinanceCommandData;
use App\Models\GstReturnPeriod;
use App\Services\Finance\GstComplianceService;
final class PrepareGstReturnPeriod
{
    public function __construct(private readonly GstComplianceService $gst) {}
    public function execute(FinanceCommandData $command): GstReturnPeriod { return $this->gst->prepareReturnPeriod($command->attributes,$command->actor,$command->request); }
}
