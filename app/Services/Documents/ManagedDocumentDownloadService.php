<?php

namespace App\Services\Documents;

use App\Models\ManagedDocument;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ManagedDocumentDownloadService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function download(ManagedDocument $document, User $actor, ?Request $request = null): StreamedResponse
    {
        $disk = $document->storage_disk ?: 'local';
        $storage = Storage::disk($disk);

        if (! $storage->exists($document->storage_path)) {
            throw new NotFoundHttpException('The requested document file is not available.');
        }

        $this->auditLogger->record(
            $actor,
            'documents.document.downloaded',
            'Downloaded managed document',
            $document,
            [
                'document_number' => $document->document_number,
                'owner_type' => $document->owner_type,
                'owner_id' => $document->owner_id,
                'storage_disk' => $disk,
                'mime_type' => $document->mime_type,
                'file_size_bytes' => $document->file_size_bytes,
            ],
            $request,
        );

        return $storage->download(
            $document->storage_path,
            $document->original_filename,
            [
                'Content-Type' => $document->mime_type,
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
