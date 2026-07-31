<?php

namespace App\Application\Payroll\Actions;

use App\Application\Payroll\Data\PayrollCommandData;
use App\Models\PayrollBankTransferBatch;
use App\Services\Payroll\PayrollBankTransferService;

final class ReleasePayrollBankBatch
{
    public function __construct(private readonly PayrollBankTransferService $service) {}

    public function execute(PayrollBankTransferBatch $batch, PayrollCommandData $command): PayrollBankTransferBatch
    {
        return $this->service->release($batch, $command->attributes, $command->actor, $command->request);
    }
}
