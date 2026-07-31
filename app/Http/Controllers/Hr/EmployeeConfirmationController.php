<?php

namespace App\Http\Controllers\Hr;

use App\Application\Hr\Actions\CreateConfirmationCase;
use App\Application\Hr\Actions\DecideConfirmationCase;
use App\Application\Hr\Actions\ListConfirmationCases;
use App\Application\Hr\Actions\ListConfirmationWorkspace;
use App\Application\Hr\Actions\SubmitConfirmationRecommendation;
use App\Application\Hr\Data\ConfirmationRecommendationData;
use App\Application\Hr\Data\HrCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ConfirmationCaseIndexRequest;
use App\Http\Requests\Hr\DecideConfirmationCaseRequest;
use App\Http\Requests\Hr\RecommendConfirmationCaseRequest;
use App\Http\Requests\Hr\StoreConfirmationCaseRequest;
use App\Http\Resources\EmployeeConfirmationCaseResource;
use App\Models\EmployeeConfirmationCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class EmployeeConfirmationController extends Controller
{
    public function index(ConfirmationCaseIndexRequest $request, ListConfirmationCases $list, ListConfirmationWorkspace $workspace): AnonymousResourceCollection|View
    {
        if (! $request->wantsJson()) {
            return view('hr.confirmation.index', $workspace->execute($request->user(), $request->validated())->toView());
        }

        return EmployeeConfirmationCaseResource::collection($list->execute($request->user(), $request->validated()));
    }

    public function store(StoreConfirmationCaseRequest $request, CreateConfirmationCase $create): JsonResponse|RedirectResponse
    {
        $case = $create->execute(new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()->route('hr.confirmation-cases.index')->with('status', 'Confirmation case '.$case->case_number.' created.');
        }

        return (new EmployeeConfirmationCaseResource($case))
            ->additional(['message' => 'Employee confirmation case created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function recommend(
        EmployeeConfirmationCase $employeeConfirmationCase,
        RecommendConfirmationCaseRequest $request,
        SubmitConfirmationRecommendation $action,
    ): EmployeeConfirmationCaseResource|RedirectResponse {
        $case = $action->execute(
            $employeeConfirmationCase,
            ConfirmationRecommendationData::from($request->validated()),
            $request->user(),
            $request,
        );

        return $request->wantsJson() ? (new EmployeeConfirmationCaseResource($case))->additional(['message' => 'Manager confirmation recommendation submitted.']) : redirect()->route('hr.confirmation-cases.index')->with('status', 'Manager recommendation submitted.');
    }

    public function decide(
        EmployeeConfirmationCase $employeeConfirmationCase,
        DecideConfirmationCaseRequest $request,
        DecideConfirmationCase $decide,
    ): EmployeeConfirmationCaseResource|RedirectResponse {
        $case = $decide->execute($employeeConfirmationCase, new HrCommandData($request->validated(), $request->user(), $request));

        return $request->wantsJson() ? (new EmployeeConfirmationCaseResource($case))->additional(['message' => 'HR confirmation decision recorded.']) : redirect()->route('hr.confirmation-cases.index')->with('status', 'HR confirmation decision recorded.');
    }
}
