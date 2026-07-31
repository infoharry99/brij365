<?php

namespace App\Application\Payroll\Actions;

use App\Application\Payroll\Data\PayrollCommandData;
use App\Models\PayrollBankTransferBatch;
use App\Models\PayrollRun;
use App\Services\Payroll\PayrollBankTransferService;

final class PreparePayrollBankBatch
{
    public function __construct(private readonly PayrollBankTransferService $service) {}

    public function execute(PayrollRun $run, PayrollCommandData $command): PayrollBankTransferBatch
    {
        return $this->service->prepare($run, $command->attributes, $command->actor, $command->request);
    }
}
