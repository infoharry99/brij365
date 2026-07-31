<?php

namespace App\Http\Controllers\Finance;

use App\Application\Finance\Actions\ViewFinanceDashboard;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FinanceDashboardRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class FinanceDashboardController extends Controller
{
    public function __invoke(
        FinanceDashboardRequest $request,
        ViewFinanceDashboard $viewDashboard,
    ): JsonResponse|View
    {
        $page = $viewDashboard->execute($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $page->dashboard,
            ]);
        }

        return view('finance.dashboard', $page->toView());
    }
}
