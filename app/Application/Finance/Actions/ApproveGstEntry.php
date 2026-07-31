<?php
namespace App\Application\Finance\Actions;
use App\Application\Finance\Data\FinanceCommandData;
use App\Models\GstEntry;
use App\Services\Finance\GstComplianceService;
final class ApproveGstEntry
{
    public function __construct(private readonly GstComplianceService $gst) {}
    public function execute(GstEntry $entry,FinanceCommandData $command): GstEntry { return $this->gst->approveEntry($entry,$command->attributes,$command->actor,$command->request); }
}
