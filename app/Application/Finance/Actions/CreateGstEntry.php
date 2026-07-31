<?php
namespace App\Application\Finance\Actions;
use App\Application\Finance\Data\FinanceCommandData;
use App\Models\GstEntry;
use App\Services\Finance\GstComplianceService;
final class CreateGstEntry
{
    public function __construct(private readonly GstComplianceService $gst) {}
    public function execute(FinanceCommandData $command): GstEntry { return $this->gst->createEntry($command->attributes,$command->actor,$command->request); }
}
