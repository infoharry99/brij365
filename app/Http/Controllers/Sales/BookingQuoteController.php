<?php

namespace App\Http\Controllers\Sales;

use App\Application\Sales\Actions\QuoteBooking;
use App\Application\Sales\Data\SalesCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\BookingQuoteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class BookingQuoteController extends Controller
{
    public function __invoke(BookingQuoteRequest $request, QuoteBooking $action): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $quote = $action->execute(new SalesCommandData($data, $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('sales.bookings.index')
                ->withInput($request->only(['project_unit_id', 'quoted_on', 'discount_amount']))
                ->with('quote', $quote);
        }

        return response()->json([
            'data' => $quote,
        ]);
    }
}
