<?php

namespace App\Application\Payroll\Actions;

use App\Application\Payroll\Data\PayrollCommandData;
use App\Models\PayrollRun;
use App\Services\Payroll\PayrollRunService;

final class ApprovePayrollRun
{
    public function __construct(private readonly PayrollRunService $service) {}

    public function execute(PayrollRun $run, PayrollCommandData $command): PayrollRun
    {
        return $this->service->approve($run, $command->actor, $command->attributes, $command->request);
    }
}
