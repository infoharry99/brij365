<?php

namespace App\Http\Controllers\Hr;

use App\Application\Hr\Actions\ListEmployeeAuditEvents;
use App\Application\Hr\Actions\ListEmployeeAuditPage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\EmployeeAuditEventIndexRequest;
use App\Http\Resources\AuditEventResource;
use App\Models\Employee;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class EmployeeAuditEventController extends Controller
{
    public function index(EmployeeAuditEventIndexRequest $request, Employee $employee, ListEmployeeAuditEvents $list, ListEmployeeAuditPage $page): AnonymousResourceCollection|View
    {
        if (! $request->wantsJson()) {
            return view('hr.employees.audit', $page->execute($employee, $request->user(), $request->validated())->toView());
        }

        return AuditEventResource::collection($list->execute($employee, $request->validated()));
    }
}
