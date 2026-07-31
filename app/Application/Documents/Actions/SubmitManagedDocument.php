<?php

namespace App\Application\Documents\Actions;

use App\Application\Documents\Data\DocumentCommandData;
use App\Models\ManagedDocument;
use App\Services\Documents\ManagedDocumentService;

final class SubmitManagedDocument
{
    public function __construct(private readonly ManagedDocumentService $documents) {}

    public function execute(DocumentCommandData $command): ManagedDocument
    {
        return $this->documents->submit($command->attributes, $command->actor, $command->request);
    }
}
