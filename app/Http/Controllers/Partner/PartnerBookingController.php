<?php

namespace App\Http\Controllers\Partner;

use App\Application\Partner\Actions\ListPartnerBookings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\PartnerBookingIndexRequest;
use App\Http\Resources\BookingResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class PartnerBookingController extends Controller
{
    public function index(PartnerBookingIndexRequest $request, ListPartnerBookings $list): AnonymousResourceCollection|View
    {
        $workspace = $list->execute($request->user(), $request->validated());

        return $request->wantsJson()
            ? BookingResource::collection($workspace->records)
            : view('partner.bookings', $workspace->toView());
    }
}
