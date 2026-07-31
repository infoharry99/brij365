<?php

namespace App\Application\Payroll\Actions;

use App\Application\Payroll\Data\PayrollCommandData;
use App\Models\CommissionRun;
use App\Services\Payroll\CommissionService;

final class GenerateCommissionRun
{
    public function __construct(private readonly CommissionService $service) {}

    public function execute(PayrollCommandData $command): CommissionRun
    {
        return $this->service->generateRun($command->attributes, $command->actor, $command->request);
    }
}
