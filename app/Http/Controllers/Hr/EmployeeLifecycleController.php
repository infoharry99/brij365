<?php

namespace App\Http\Controllers\Hr;

use App\Application\Hr\Actions\ListLifecycleWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\LifecycleIndexRequest;
use Illuminate\View\View;

class EmployeeLifecycleController extends Controller
{
    public function index(LifecycleIndexRequest $request, ListLifecycleWorkspace $workspace): View
    {
        return view('hr.lifecycle.index', $workspace->execute($request->user(), $request->validated())->toView());
    }
}
