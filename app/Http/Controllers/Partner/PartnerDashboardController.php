<?php

namespace App\Http\Controllers\Partner;

use App\Application\Partner\Actions\ViewPartnerPortalSummary;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\PartnerDashboardRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PartnerDashboardController extends Controller
{
    public function show(PartnerDashboardRequest $request, ViewPartnerPortalSummary $view): JsonResponse|View
    {
        $page = $view->execute($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return response()->json(['data' => $page->summary]);
        }

        return view('partner.summary', $page->toView());
    }
}
