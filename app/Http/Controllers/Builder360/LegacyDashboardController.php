<?php

namespace App\Http\Controllers\Builder360;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class LegacyDashboardController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->route('builder360.dashboard');
    }
}
