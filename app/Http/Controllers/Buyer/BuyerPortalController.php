<?php

namespace App\Http\Controllers\Buyer;

use App\Application\AfterSales\Actions\CloseServiceTicketAction;
use App\Application\AfterSales\Actions\CreateServiceTicket;
use App\Application\AfterSales\Data\AfterSalesCommandData;
use App\Application\AfterSales\Data\CloseServiceTicketData;
use App\Application\Buyer\Actions\ListBuyerBookings;
use App\Application\Buyer\Actions\ListBuyerDocuments;
use App\Application\Buyer\Actions\ListBuyerPaymentRequests;
use App\Application\Buyer\Actions\ListBuyerReceipts;
use App\Application\Buyer\Actions\ListBuyerServiceTickets;
use App\Application\Buyer\Actions\PayBuyerPaymentRequest;
use App\Application\Buyer\Actions\ViewBuyerPortalSummary;
use App\Application\Finance\Data\FinanceCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\BuyerPortalIndexRequest;
use App\Http\Requests\Buyer\BuyerPortalSummaryRequest;
use App\Http\Requests\Buyer\CloseBuyerServiceTicketRequest;
use App\Http\Requests\Buyer\PayPaymentRequestRequest;
use App\Http\Requests\Buyer\StoreBuyerServiceTicketRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\CollectionReceiptResource;
use App\Http\Resources\ManagedDocumentResource;
use App\Http\Resources\PaymentRequestResource;
use App\Http\Resources\ServiceTicketResource;
use App\Models\PaymentRequest;
use App\Models\ServiceTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class BuyerPortalController extends Controller
{
    public function summary(BuyerPortalSummaryRequest $request, ViewBuyerPortalSummary $view): JsonResponse|View
    {
        $page = $view->execute($request->user(), $request);

        if ($request->wantsJson()) {
            return response()->json(['data' => $page->summary]);
        }

        return view('buyer.summary', $page->toView());
    }

    public function bookings(BuyerPortalIndexRequest $request, ListBuyerBookings $list): AnonymousResourceCollection|View
    {
        $workspace = $list->execute($request->user(), $request->validated());

        return $request->wantsJson()
            ? BookingResource::collection($workspace->records)
            : view('buyer.bookings', $workspace->toView());
    }

    public function receipts(BuyerPortalIndexRequest $request, ListBuyerReceipts $list): AnonymousResourceCollection|View
    {
        $workspace = $list->execute($request->user(), $request->validated());

        return $request->wantsJson()
            ? CollectionReceiptResource::collection($workspace->records)
            : view('buyer.receipts', $workspace->toView());
    }

    public function paymentRequests(BuyerPortalIndexRequest $request, ListBuyerPaymentRequests $list): AnonymousResourceCollection|View
    {
        $workspace = $list->execute($request->user(), $request->validated());

        return $request->wantsJson()
            ? PaymentRequestResource::collection($workspace->records)
            : view('buyer.payment-requests', $workspace->toView());
    }

    public function payPaymentRequest(
        PaymentRequest $paymentRequest,
        PayPaymentRequestRequest $request,
        PayBuyerPaymentRequest $pay,
    ): PaymentRequestResource|RedirectResponse {
        $paid = $pay->execute($paymentRequest, new FinanceCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()->route('buyer.payment-requests.index')->with('status', "Payment request {$paid->request_number} completed.");
        }

        return new PaymentRequestResource($paid);
    }

    public function documents(BuyerPortalIndexRequest $request, ListBuyerDocuments $list): AnonymousResourceCollection|View
    {
        $workspace = $list->execute($request->user(), $request->validated());

        return $request->wantsJson()
            ? ManagedDocumentResource::collection($workspace->records)
            : view('buyer.documents', $workspace->toView());
    }

    public function tickets(BuyerPortalIndexRequest $request, ListBuyerServiceTickets $list): AnonymousResourceCollection|View
    {
        $workspace = $list->execute($request->user(), $request->validated());

        return $request->wantsJson()
            ? ServiceTicketResource::collection($workspace->records)
            : view('buyer.service-tickets', $workspace->toView());
    }

    public function storeTicket(StoreBuyerServiceTicketRequest $request, CreateServiceTicket $create): ServiceTicketResource|RedirectResponse
    {
        $ticket = $create->execute(new AfterSalesCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()->route('buyer.summary')->with('status', "Service ticket {$ticket->ticket_number} created.");
        }

        return (new ServiceTicketResource($ticket))->additional(['message' => 'Buyer service ticket created.']);
    }

    public function closeTicket(
        ServiceTicket $serviceTicket,
        CloseBuyerServiceTicketRequest $request,
        CloseServiceTicketAction $action,
    ): ServiceTicketResource|RedirectResponse {
        $ticket = $action->execute($serviceTicket, CloseServiceTicketData::from($request->validated()), $request->user(), $request);

        if (! $request->wantsJson()) {
            return redirect()->route('buyer.summary')->with('status', "Service ticket {$ticket->ticket_number} closed.");
        }

        return (new ServiceTicketResource($ticket))->additional(['message' => 'Buyer service ticket closed.']);
    }
}
