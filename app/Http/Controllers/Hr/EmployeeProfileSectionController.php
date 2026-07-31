<?php

namespace App\Http\Controllers\Hr;

use App\Application\Hr\Actions\UpdateEmployeeProfileSections;
use App\Application\Hr\Actions\ViewEmployeeProfileSections;
use App\Application\Hr\Actions\ViewEmployeeProfileSectionsPage;
use App\Application\Hr\Data\HrCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\EmployeeProfileSectionIndexRequest;
use App\Http\Requests\Hr\UpdateEmployeeProfileSectionsRequest;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmployeeProfileSectionController extends Controller
{
    public function show(
        EmployeeProfileSectionIndexRequest $request,
        Employee $employee,
        ViewEmployeeProfileSections $view,
        ViewEmployeeProfileSectionsPage $page,
    ): JsonResponse|View {
        if (! $request->wantsJson()) {
            return view('hr.employees.profile-sections', $page->execute($employee, $request->user())->toView());
        }

        return response()->json([
            'data' => $view->execute($employee, $request->user()),
        ]);
    }

    public function update(
        UpdateEmployeeProfileSectionsRequest $request,
        Employee $employee,
        UpdateEmployeeProfileSections $update,
    ): JsonResponse|RedirectResponse {
        $sections = $update->execute($employee, new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()->route('hr.employees.profile-sections.show', $employee)->with('status', 'Employee profile sections updated.');
        }

        return response()->json([
            'message' => 'Employee profile sections updated.',
            'data' => $sections,
        ]);
    }
}
