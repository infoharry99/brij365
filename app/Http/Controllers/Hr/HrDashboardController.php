<?php

namespace App\Http\Controllers\Hr;

use App\Application\Hr\Actions\ViewHrCommandCenter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\HrDashboardRequest;
use Illuminate\View\View;

final class HrDashboardController extends Controller
{
    public function __invoke(HrDashboardRequest $request, ViewHrCommandCenter $view): View
    {
        return view('hr.dashboard.index', [
            'dashboard' => $view->execute($request->user())->toView(),
        ]);
    }
}
