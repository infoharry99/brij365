<?php

namespace App\Application\Payroll\Actions;

use App\Application\Payroll\Data\PayrollCommandData;
use App\Models\EmployeeTaxDocument;
use App\Services\Payroll\TaxDocumentService;

final class GenerateTaxDocument
{
    public function __construct(private readonly TaxDocumentService $service) {}

    public function execute(PayrollCommandData $command): EmployeeTaxDocument
    {
        return $this->service->generate($command->attributes, $command->actor, $command->request);
    }
}
