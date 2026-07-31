<?php

namespace App\Application\Payroll\Actions;

use App\Application\Payroll\Data\PayrollCommandData;
use App\Models\CommissionRun;
use App\Services\Payroll\CommissionService;

final class RejectCommissionRun
{
    public function __construct(private readonly CommissionService $service) {}

    public function execute(CommissionRun $run, PayrollCommandData $command): CommissionRun
    {
        return $this->service->rejectRun($run, $command->attributes, $command->actor, $command->request);
    }
}
