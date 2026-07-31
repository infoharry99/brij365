<?php

namespace App\Http\Controllers\Hr;

use App\Application\Hr\Actions\ViewEmployeePayrollSummary;
use App\Application\Hr\Actions\ViewEmployeePayrollSummaryPage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\EmployeePayrollSummaryRequest;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class EmployeePayrollSummaryController extends Controller
{
    public function show(EmployeePayrollSummaryRequest $request, Employee $employee, ViewEmployeePayrollSummary $view, ViewEmployeePayrollSummaryPage $page): JsonResponse|View
    {
        if (! $request->wantsJson()) {
            return view('hr.employees.payroll-summary', $page->execute($employee, $request->user())->toView());
        }

        return response()->json(['data' => $view->execute($employee, $request->user())]);
    }
}
