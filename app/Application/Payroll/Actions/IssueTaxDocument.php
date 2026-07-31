<?php

namespace App\Application\Payroll\Actions;

use App\Application\Payroll\Data\PayrollCommandData;
use App\Models\EmployeeTaxDocument;
use App\Services\Payroll\TaxDocumentService;

final class IssueTaxDocument
{
    public function __construct(private readonly TaxDocumentService $service) {}

    public function execute(EmployeeTaxDocument $document, PayrollCommandData $command): EmployeeTaxDocument
    {
        return $this->service->issue($document, $command->attributes, $command->actor, $command->request);
    }
}
