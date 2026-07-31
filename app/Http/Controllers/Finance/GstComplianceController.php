<?php

namespace App\Http\Controllers\Finance;

use App\Application\Finance\Actions\ApproveGstEntry;
use App\Application\Finance\Actions\ApproveGstReturnPeriod;
use App\Application\Finance\Actions\CreateGstEntry;
use App\Application\Finance\Actions\ListGstEntryWorkspace;
use App\Application\Finance\Actions\ListGstReturnPeriodWorkspace;
use App\Application\Finance\Actions\LockGstReturnPeriod;
use App\Application\Finance\Actions\PrepareGstReturnPeriod;
use App\Application\Finance\Data\FinanceCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ApproveGstEntryRequest;
use App\Http\Requests\Finance\ApproveGstReturnPeriodRequest;
use App\Http\Requests\Finance\GstEntryIndexRequest;
use App\Http\Requests\Finance\GstReturnPeriodIndexRequest;
use App\Http\Requests\Finance\LockGstReturnPeriodRequest;
use App\Http\Requests\Finance\PrepareGstReturnPeriodRequest;
use App\Http\Requests\Finance\StoreGstEntryRequest;
use App\Http\Resources\GstEntryResource;
use App\Http\Resources\GstReturnPeriodResource;
use App\Models\GstEntry;
use App\Models\GstReturnPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class GstComplianceController extends Controller
{
    public function entries(
        GstEntryIndexRequest $request,
        ListGstEntryWorkspace $list,
    ): AnonymousResourceCollection|View
    {
        $workspace = $list->execute($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return GstEntryResource::collection($workspace->entries);
        }

        return view('finance.gst-entries.index', $workspace->toView());
    }

    public function storeEntry(StoreGstEntryRequest $request, CreateGstEntry $create): JsonResponse|RedirectResponse
    {
        $entry = $create->execute(new FinanceCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('finance.gst-entries.index')
                ->with('status', "GST entry {$entry->entry_number} submitted for approval.");
        }

        return (new GstEntryResource($entry))
            ->response()
            ->setStatusCode(201);
    }

    public function approveEntry(GstEntry $gstEntry, ApproveGstEntryRequest $request, ApproveGstEntry $approve): GstEntryResource|RedirectResponse
    {
        $entry = $approve->execute($gstEntry, new FinanceCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('finance.gst-entries.index')
                ->with('status', "GST entry {$entry->entry_number} approved.");
        }

        return new GstEntryResource($entry);
    }

    public function periods(
        GstReturnPeriodIndexRequest $request,
        ListGstReturnPeriodWorkspace $list,
    ): AnonymousResourceCollection|View
    {
        $workspace = $list->execute($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return GstReturnPeriodResource::collection($workspace->periods);
        }

        return view('finance.gst-return-periods.index', $workspace->toView());
    }

    public function preparePeriod(PrepareGstReturnPeriodRequest $request, PrepareGstReturnPeriod $prepare): JsonResponse|RedirectResponse
    {
        $period = $prepare->execute(new FinanceCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('finance.gst-return-periods.index')
                ->with('status', "GST return period {$period->return_number} prepared.");
        }

        return (new GstReturnPeriodResource($period))
            ->response()
            ->setStatusCode(201);
    }

    public function approvePeriod(GstReturnPeriod $gstReturnPeriod, ApproveGstReturnPeriodRequest $request, ApproveGstReturnPeriod $approve): GstReturnPeriodResource|RedirectResponse
    {
        $period = $approve->execute($gstReturnPeriod, new FinanceCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('finance.gst-return-periods.index')
                ->with('status', "GST return period {$period->return_number} approved.");
        }

        return new GstReturnPeriodResource($period);
    }

    public function lockPeriod(GstReturnPeriod $gstReturnPeriod, LockGstReturnPeriodRequest $request, LockGstReturnPeriod $lock): GstReturnPeriodResource|RedirectResponse
    {
        $period = $lock->execute($gstReturnPeriod, new FinanceCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('finance.gst-return-periods.index')
                ->with('status', "GST return period {$period->return_number} locked.");
        }

        return new GstReturnPeriodResource($period);
    }

}
