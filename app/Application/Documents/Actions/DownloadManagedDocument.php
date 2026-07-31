<?php

namespace App\Application\Documents\Actions;

use App\Models\ManagedDocument;
use App\Models\User;
use App\Services\Documents\ManagedDocumentDownloadService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadManagedDocument
{
    public function __construct(private readonly ManagedDocumentDownloadService $downloads) {}

    public function execute(ManagedDocument $document, User $actor, Request $request): StreamedResponse
    {
        return $this->downloads->download($document, $actor, $request);
    }
}
