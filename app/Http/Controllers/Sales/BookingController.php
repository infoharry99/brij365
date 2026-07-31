<?php

namespace App\Http\Controllers\Sales;

use App\Application\Sales\Actions\CreateBooking;
use App\Application\Sales\Actions\ListBookingWorkspace;
use App\Application\Sales\Data\SalesCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\BookingIndexRequest;
use App\Http\Requests\Sales\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(BookingIndexRequest $request, ListBookingWorkspace $action): AnonymousResourceCollection|View
    {
        $page = $action->execute($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return BookingResource::collection($page->bookings);
        }

        return view('sales.bookings.index', [
            'bookings' => $page->bookings->withQueryString(), 'filters' => $page->filters,
            'bookableUnits' => $page->bookableUnits, 'leads' => $page->leads, 'projects' => $page->projects,
            'customers' => $page->customers, 'partners' => $page->partners, 'statuses' => $page->statuses,
            'canCreateBooking' => $page->canCreate,
        ]);
    }

    public function store(StoreBookingRequest $request, CreateBooking $action): JsonResponse|RedirectResponse
    {
        $booking = $action->execute(new SalesCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('sales.bookings.index')
                ->with('status', "Booking {$booking->booking_code} created for {$booking->customer?->name}.");
        }

        return (new BookingResource($booking))
            ->response()
            ->setStatusCode(201);
    }
}
