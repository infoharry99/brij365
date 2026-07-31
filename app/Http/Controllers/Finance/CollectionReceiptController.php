<?php

namespace App\Http\Controllers\Finance;

use App\Application\Finance\Actions\ApproveCollectionReceipt;
use App\Application\Finance\Actions\ExportCollectionReceipts;
use App\Application\Finance\Actions\ListCollectionReceiptWorkspace;
use App\Application\Finance\Actions\SubmitCollectionReceipt;
use App\Application\Finance\Data\FinanceCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ApproveCollectionReceiptRequest;
use App\Http\Requests\Finance\CollectionReceiptIndexRequest;
use App\Http\Requests\Finance\StoreCollectionReceiptRequest;
use App\Http\Resources\CollectionReceiptResource;
use App\Models\CollectionReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CollectionReceiptController extends Controller
{
    public function index(CollectionReceiptIndexRequest $request, ListCollectionReceiptWorkspace $list): AnonymousResourceCollection|View
    {
        $workspace = $list->execute($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return CollectionReceiptResource::collection($workspace->receipts);
        }

        return view('finance.collections.index', $workspace->toView());
    }

    public function store(StoreCollectionReceiptRequest $request, SubmitCollectionReceipt $submit): JsonResponse|RedirectResponse
    {
        $receipt = $submit->execute(new FinanceCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('finance.collections.index')
                ->with('status', "Collection receipt {$receipt->receipt_number} submitted for approval.");
        }

        return (new CollectionReceiptResource($receipt))
            ->response()
            ->setStatusCode(201);
    }

    public function approve(
        ApproveCollectionReceiptRequest $request,
        CollectionReceipt $collectionReceipt,
        ApproveCollectionReceipt $approve,
    ): CollectionReceiptResource|RedirectResponse {
        $receipt = $approve->execute($collectionReceipt, new FinanceCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('finance.collections.index')
                ->with('status', "Collection receipt {$receipt->receipt_number} approved.");
        }

        return new CollectionReceiptResource($receipt);
    }

    public function export(
        CollectionReceiptIndexRequest $request,
        ExportCollectionReceipts $export,
    ): Response {
        return $export->execute($request->user(), $request->validated(), $request);
    }
}
