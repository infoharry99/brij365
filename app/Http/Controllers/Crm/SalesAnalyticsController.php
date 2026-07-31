<?php

namespace App\Http\Controllers\Crm;

use App\Application\Crm\Actions\ViewSalesAnalytics;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\SalesAnalyticsRequest;
use Illuminate\View\View;

final class SalesAnalyticsController extends Controller
{
    public function __invoke(SalesAnalyticsRequest $request, ViewSalesAnalytics $action): View
    {
        $page = $action->execute($request->user(), $request->validated());

        return view('crm.analytics.index', [
            'filters' => $page->filters,
            'projects' => $page->projects,
            'report' => $page->report,
        ]);
    }
}
