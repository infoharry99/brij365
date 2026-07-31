<?php

namespace App\Application\Documents\Actions;

use App\Application\Documents\Data\DocumentCommandData;
use App\Models\ManagedDocument;
use App\Services\Documents\ManagedDocumentService;

final class ApproveManagedDocument
{
    public function __construct(private readonly ManagedDocumentService $documents) {}

    public function execute(ManagedDocument $document, DocumentCommandData $command): ManagedDocument
    {
        return $this->documents->approve($document, $command->attributes, $command->actor, $command->request);
    }
}
