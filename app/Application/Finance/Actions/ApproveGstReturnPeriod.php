<?php
namespace App\Application\Finance\Actions;
use App\Application\Finance\Data\FinanceCommandData;
use App\Models\GstReturnPeriod;
use App\Services\Finance\GstComplianceService;
final class ApproveGstReturnPeriod
{
    public function __construct(private readonly GstComplianceService $gst) {}
    public function execute(GstReturnPeriod $period,FinanceCommandData $command): GstReturnPeriod { return $this->gst->approveReturnPeriod($period,$command->attributes,$command->actor,$command->request); }
}
