<?php

namespace App\Http\Controllers\Builder360;

use App\Application\Dashboard\Actions\ShowRoleDashboard;
use App\Application\Dashboard\Data\DashboardContextData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Builder360\DashboardRequest;
use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    public function __invoke(DashboardRequest $request): RedirectResponse
    {
        return redirect()->route('collaboration.tasks.index');
    }
}
