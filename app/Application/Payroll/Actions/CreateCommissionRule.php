<?php

namespace App\Application\Payroll\Actions;

use App\Application\Payroll\Data\PayrollCommandData;
use App\Models\CommissionRule;
use App\Services\Payroll\CommissionService;

final class CreateCommissionRule
{
    public function __construct(private readonly CommissionService $service) {}

    public function execute(PayrollCommandData $command): CommissionRule
    {
        return $this->service->createRule($command->attributes, $command->actor, $command->request);
    }
}
