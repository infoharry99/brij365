<?php

namespace App\Http\Controllers\Finance;

use App\Application\Finance\Actions\CancelPaymentRequest;
use App\Application\Finance\Actions\CreatePaymentRequest;
use App\Application\Finance\Actions\ListPaymentRequestWorkspace;
use App\Application\Finance\Data\FinanceCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CancelPaymentRequestRequest;
use App\Http\Requests\Finance\PaymentRequestIndexRequest;
use App\Http\Requests\Finance\StorePaymentRequestRequest;
use App\Http\Resources\PaymentRequestResource;
use App\Models\PaymentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class PaymentRequestController extends Controller
{
    public function index(PaymentRequestIndexRequest $request, ListPaymentRequestWorkspace $list): AnonymousResourceCollection|View
    {
        abort_if(in_array($request->user()?->role?->slug, ['buyer', 'customer'], true), 403);

        $workspace = $list->execute($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return PaymentRequestResource::collection($workspace->paymentRequests);
        }

        return view('finance.payment-requests.index', $workspace->toView());
    }

    public function store(
        StorePaymentRequestRequest $request,
        CreatePaymentRequest $create,
    ): JsonResponse|RedirectResponse {
        $paymentRequest = $create->execute(new FinanceCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('finance.payment-requests.index')
                ->with('status', "Payment request {$paymentRequest->request_number} created.");
        }

        return (new PaymentRequestResource($paymentRequest))
            ->response()
            ->setStatusCode(201);
    }

    public function cancel(
        PaymentRequest $paymentRequest,
        CancelPaymentRequestRequest $request,
        CancelPaymentRequest $cancel,
    ): PaymentRequestResource|RedirectResponse {
        $cancelled = $cancel->execute($paymentRequest, new FinanceCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('finance.payment-requests.index')
                ->with('status', "Payment request {$cancelled->request_number} cancelled.");
        }

        return new PaymentRequestResource($cancelled);
    }

}
