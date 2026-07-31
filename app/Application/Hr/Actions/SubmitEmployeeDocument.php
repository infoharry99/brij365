<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\ManagedDocument;
use App\Services\Documents\ManagedDocumentService;

final class SubmitEmployeeDocument
{
    public function __construct(private readonly ManagedDocumentService $service) {}

    public function execute(HrCommandData $c): ManagedDocument
    {
        return $this->service->submit($c->attributes, $c->actor, $c->request);
    }
}
