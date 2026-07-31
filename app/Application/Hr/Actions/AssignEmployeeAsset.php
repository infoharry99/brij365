<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\EmployeeAsset;
use App\Services\Hr\EmployeeOperationsService;

final class AssignEmployeeAsset
{
    public function __construct(private readonly EmployeeOperationsService $service) {}

    public function execute(EmployeeAsset $asset, HrCommandData $c): EmployeeAsset
    {
        return $this->service->assignAsset($asset, $c->attributes, $c->actor, $c->request);
    }
}
