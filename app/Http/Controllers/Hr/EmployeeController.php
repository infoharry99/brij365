<?php

namespace App\Http\Controllers\Hr;

use App\Application\Hr\Actions\CreateEmployee;
use App\Application\Hr\Actions\ExportEmployeeRegister;
use App\Application\Hr\Actions\ListEmployeeWorkspace;
use App\Application\Hr\Actions\UpdateEmployee;
use App\Application\Hr\Actions\ViewEmployeeSelfServiceDashboard;
use App\Application\Hr\Actions\ViewEmployeeProfilePage;
use App\Application\Hr\Actions\ViewMyEmployeeProfilePage;
use App\Application\Hr\Data\HrCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\EmployeeIndexRequest;
use App\Http\Requests\Hr\EmployeeReportExportRequest;
use App\Http\Requests\Hr\MyEmployeeProfileRequest;
use App\Http\Requests\Hr\ShowEmployeeRequest;
use App\Http\Requests\Hr\StoreEmployeeRequest;
use App\Http\Requests\Hr\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmployeeController extends Controller
{
    public function index(EmployeeIndexRequest $request, ListEmployeeWorkspace $workspace): AnonymousResourceCollection|View
    {
        $data = $workspace->execute($request->user(), $request->validated());

        if (! $request->wantsJson()) {
            return view('hr.employees.index', $data->toView());
        }

        return EmployeeResource::collection($data->employees);
    }

    public function export(EmployeeReportExportRequest $request, ExportEmployeeRegister $export): Response
    {
        return $export->execute(new HrCommandData($request->validated(), $request->user(), $request));
    }

    public function me(MyEmployeeProfileRequest $request, ViewEmployeeSelfServiceDashboard $view): EmployeeResource|View
    {
        $data = $view->execute($request->user());

        if (! $data) {
            throw new NotFoundHttpException('Employee profile is not linked to the current user.');
        }

        if (! $request->wantsJson()) {
            return view('hr.employees.self-service', [
                'selfService' => $data->toView(),
                'employee' => $data->employee,
            ]);
        }

        return new EmployeeResource($data->employee);
    }

    public function myProfile(MyEmployeeProfileRequest $request, ViewMyEmployeeProfilePage $view): EmployeeResource|View
    {
        $data = $view->execute($request->user());

        if (! $data) {
            throw new NotFoundHttpException('Employee profile is not linked to the current user.');
        }

        if (! $request->wantsJson()) {
            return view('hr.employees.show', $data->toView());
        }

        return new EmployeeResource($data->employee);
    }

    public function show(ShowEmployeeRequest $request, Employee $employee, ViewEmployeeProfilePage $view): EmployeeResource|View
    {
        $data = $view->execute($employee, $request->user());

        if (! $request->wantsJson()) {
            return view('hr.employees.show', $data->toView());
        }

        return new EmployeeResource($data->employee);
    }

    public function store(StoreEmployeeRequest $request, CreateEmployee $create): JsonResponse|RedirectResponse
    {
        $employee = $create->execute(new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()->route('hr.employees.show', $employee)->with('status', 'Employee master record '.$employee->employee_code.' created.');
        }

        return (new EmployeeResource($employee))
            ->additional(['message' => 'Employee master record created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        Employee $employee,
        UpdateEmployeeRequest $request,
        UpdateEmployee $update,
    ): EmployeeResource|RedirectResponse {
        $updatedEmployee = $update->execute($employee, new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()->route('hr.employees.show', $updatedEmployee)->with('status', 'Employee master record updated.');
        }

        return (new EmployeeResource($updatedEmployee))->additional(['message' => 'Employee master record updated.']);
    }
}
