<?php

namespace App\Http\Controllers\Hr;

use App\Application\Hr\Actions\ViewHrReportCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\HrReportIndexRequest;
use Illuminate\View\View;

final class HrReportController extends Controller
{
    public function __invoke(HrReportIndexRequest $request, ViewHrReportCatalog $view): View
    {
        return view('hr.reports.index', [
            'catalog' => $view->execute($request->user())->toView(),
        ]);
    }
}
