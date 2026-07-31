<?php

namespace App\Http\Controllers\Builder360;

use App\Http\Controllers\Controller;
use App\Services\Builder360\Builder360Bootstrap;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassicDashboardController extends Controller
{
    public function __invoke(Request $request, Builder360Bootstrap $bootstrap): View
    {
        $roleSlug = $request->session()->get('builder360.selected_role_slug');
        $projectId = $request->session()->get('builder360.selected_project_id');
        $dashboardPeriod = $request->session()->get('builder360.dashboard_period');

        return view('builder360.classic.dashboard', [
            'bootstrap' => $bootstrap->forRoleContext(
                $request->user(),
                is_string($roleSlug) ? $roleSlug : null,
                is_numeric($projectId) ? (int) $projectId : null,
                is_array($dashboardPeriod) ? $dashboardPeriod : null,
            ),
        ]);
    }
}
