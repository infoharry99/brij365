<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\EmployeeAsset;
use App\Services\Hr\EmployeeOperationsService;

final class CreateEmployeeAsset
{
    public function __construct(private readonly EmployeeOperationsService $service) {}

    public function execute(HrCommandData $c): EmployeeAsset
    {
        return $this->service->createAsset($c->attributes, $c->actor, $c->request);
    }
}
