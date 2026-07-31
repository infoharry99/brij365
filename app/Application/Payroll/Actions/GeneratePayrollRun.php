<?php

namespace App\Application\Payroll\Actions;

use App\Application\Payroll\Data\PayrollCommandData;
use App\Models\PayrollRun;
use App\Services\Payroll\PayrollRunService;

final class GeneratePayrollRun
{
    public function __construct(private readonly PayrollRunService $service) {}

    public function execute(PayrollCommandData $command): PayrollRun
    {
        return $this->service->generate($command->attributes, $command->actor, $command->request);
    }
}
