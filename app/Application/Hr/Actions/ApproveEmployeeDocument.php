<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\ManagedDocument;
use App\Services\Documents\ManagedDocumentService;

final class ApproveEmployeeDocument
{
    public function __construct(private readonly ManagedDocumentService $service) {}

    public function execute(ManagedDocument $document, HrCommandData $c): ManagedDocument
    {
        return $this->service->approve($document, $c->attributes, $c->actor, $c->request);
    }
}
