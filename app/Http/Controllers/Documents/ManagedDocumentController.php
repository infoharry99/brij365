<?php

namespace App\Http\Controllers\Documents;

use App\Application\Documents\Actions\ApproveManagedDocument;
use App\Application\Documents\Actions\DownloadManagedDocument;
use App\Application\Documents\Actions\ListManagedDocumentWorkspace;
use App\Application\Documents\Actions\SubmitManagedDocument;
use App\Application\Documents\Data\DocumentCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\ApproveManagedDocumentRequest;
use App\Http\Requests\Documents\DownloadManagedDocumentRequest;
use App\Http\Requests\Documents\ManagedDocumentIndexRequest;
use App\Http\Requests\Documents\StoreManagedDocumentRequest;
use App\Http\Resources\ManagedDocumentResource;
use App\Models\ManagedDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManagedDocumentController extends Controller
{
    public function index(ManagedDocumentIndexRequest $request, ListManagedDocumentWorkspace $list): AnonymousResourceCollection|View
    {
        $workspace = $list->execute($request->user(), $request->validated());

        if (! $request->wantsJson()) {
            return view('documents.managed-documents.index', $workspace->toView());
        }

        return ManagedDocumentResource::collection($workspace->documents);
    }

    public function store(StoreManagedDocumentRequest $request, SubmitManagedDocument $submit): JsonResponse|RedirectResponse
    {
        $document = $submit->execute(new DocumentCommandData($request->validated(), $request->user(), $request));

        if ($request->input('_return_to') === 'documents.index') {
            return redirect()
                ->route('documents.index')
                ->with('status', "Document {$document->document_number} submitted successfully.");
        }

        return (new ManagedDocumentResource($document))
            ->response()
            ->setStatusCode(201);
    }

    public function approve(
        ApproveManagedDocumentRequest $request,
        ManagedDocument $managedDocument,
        ApproveManagedDocument $approve,
    ): ManagedDocumentResource|RedirectResponse {
        $document = $approve->execute($managedDocument, new DocumentCommandData($request->validated(), $request->user(), $request));

        if ($request->input('_return_to') === 'documents.index') {
            return redirect()
                ->route('documents.index')
                ->with('status', "Document {$document->document_number} approved successfully.");
        }

        return new ManagedDocumentResource($document);
    }

    public function download(
        DownloadManagedDocumentRequest $request,
        ManagedDocument $managedDocument,
        DownloadManagedDocument $download,
    ): StreamedResponse {
        return $download->execute($managedDocument, $request->user(), $request);
    }
}
