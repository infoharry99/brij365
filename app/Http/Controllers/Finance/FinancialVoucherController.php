<?php

namespace App\Http\Controllers\Finance;

use App\Application\Finance\Actions\ApproveFinancialVoucher;
use App\Application\Finance\Actions\ListFinancialVoucherWorkspace;
use App\Application\Finance\Actions\RejectFinancialVoucher;
use App\Application\Finance\Actions\SubmitFinancialVoucher;
use App\Application\Finance\Data\FinanceCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ApproveFinancialVoucherRequest;
use App\Http\Requests\Finance\FinancialVoucherIndexRequest;
use App\Http\Requests\Finance\RejectFinancialVoucherRequest;
use App\Http\Requests\Finance\StoreFinancialVoucherRequest;
use App\Http\Resources\FinancialVoucherResource;
use App\Models\FinancialVoucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class FinancialVoucherController extends Controller
{
    public function index(
        FinancialVoucherIndexRequest $request,
        ListFinancialVoucherWorkspace $list,
    ): AnonymousResourceCollection|View
    {
        $workspace = $list->execute($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return FinancialVoucherResource::collection($workspace->vouchers);
        }

        return view('finance.vouchers.index', $workspace->toView());
    }

    public function store(StoreFinancialVoucherRequest $request, SubmitFinancialVoucher $submit): JsonResponse|RedirectResponse
    {
        $voucher = $submit->execute(new FinanceCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('finance.vouchers.index')
                ->with('status', "Financial voucher {$voucher->voucher_number} submitted for approval.");
        }

        return (new FinancialVoucherResource($voucher))
            ->response()
            ->setStatusCode(201);
    }

    public function approve(
        ApproveFinancialVoucherRequest $request,
        FinancialVoucher $financialVoucher,
        ApproveFinancialVoucher $approve,
    ): FinancialVoucherResource|RedirectResponse {
        $voucher = $approve->execute($financialVoucher, new FinanceCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('finance.vouchers.index')
                ->with('status', "Financial voucher {$voucher->voucher_number} approved.");
        }

        return new FinancialVoucherResource($voucher);
    }

    public function reject(
        RejectFinancialVoucherRequest $request,
        FinancialVoucher $financialVoucher,
        RejectFinancialVoucher $reject,
    ): FinancialVoucherResource|RedirectResponse {
        $voucher = $reject->execute($financialVoucher, new FinanceCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('finance.vouchers.index')
                ->with('status', "Financial voucher {$voucher->voucher_number} rejected.");
        }

        return new FinancialVoucherResource($voucher);
    }

}
