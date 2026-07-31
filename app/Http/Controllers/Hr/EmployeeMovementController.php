<?php

namespace App\Http\Controllers\Hr;

use App\Application\Hr\Actions\ApproveEmployeeMovement;
use App\Application\Hr\Actions\CreateEmployeeMovement;
use App\Application\Hr\Actions\ListEmployeeMovements;
use App\Application\Hr\Actions\ListEmployeeMovementWorkspace;
use App\Application\Hr\Data\HrCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ApproveEmployeeMovementRequest;
use App\Http\Requests\Hr\EmployeeMovementIndexRequest;
use App\Http\Requests\Hr\StoreEmployeeMovementRequest;
use App\Http\Resources\EmployeeMovementResource;
use App\Models\Employee;
use App\Models\EmployeeMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class EmployeeMovementController extends Controller
{
    public function index(EmployeeMovementIndexRequest $request, Employee $employee, ListEmployeeMovements $list, ListEmployeeMovementWorkspace $workspace): AnonymousResourceCollection|View
    {
        if (! $request->wantsJson()) {
            return view('hr.employees.movements', $workspace->execute($employee, $request->user(), $request->validated())->toView());
        }

        return EmployeeMovementResource::collection($list->execute($employee, $request->validated()));
    }

    public function store(
        StoreEmployeeMovementRequest $request,
        Employee $employee,
        CreateEmployeeMovement $create,
    ): JsonResponse|RedirectResponse {
        $movement = $create->execute($employee, new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()->route('hr.employees.movements.index', $employee)->with('status', $movement->status === 'approved' ? 'Employee movement recorded and applied.' : 'Employee movement submitted for approval.');
        }

        return (new EmployeeMovementResource($movement))
            ->additional(['message' => $movement->status === 'approved' ? 'Employee movement recorded and applied.' : 'Employee movement submitted for approval.'])
            ->response()
            ->setStatusCode(201);
    }

    public function approve(
        ApproveEmployeeMovementRequest $request,
        Employee $employee,
        EmployeeMovement $employeeMovement,
        ApproveEmployeeMovement $approve,
    ): EmployeeMovementResource|RedirectResponse {
        $movement = $approve->execute($employee, $employeeMovement, new HrCommandData($request->validated(), $request->user(), $request));

        return $request->wantsJson() ? (new EmployeeMovementResource($movement))->additional(['message' => 'Employee movement approved and applied.']) : redirect()->route('hr.employees.movements.index', $employee)->with('status', 'Employee movement approved and applied.');
    }
}
