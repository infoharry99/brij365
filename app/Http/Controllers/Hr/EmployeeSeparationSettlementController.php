<?php

namespace App\Http\Controllers\Hr;

use App\Application\Hr\Actions\ApproveSeparationSettlementByFinance;
use App\Application\Hr\Actions\ApproveSeparationSettlementByHr;
use App\Application\Hr\Actions\CompleteSeparationSettlement;
use App\Application\Hr\Actions\InitiateSeparationSettlement;
use App\Application\Hr\Actions\ListSeparationSettlements;
use App\Application\Hr\Actions\ListSeparationWorkspace;
use App\Application\Hr\Data\HrCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ApproveSeparationSettlementRequest;
use App\Http\Requests\Hr\CompleteSeparationSettlementRequest;
use App\Http\Requests\Hr\FinanceApproveSeparationSettlementRequest;
use App\Http\Requests\Hr\SeparationSettlementIndexRequest;
use App\Http\Requests\Hr\StoreSeparationSettlementRequest;
use App\Http\Resources\EmployeeSeparationSettlementResource;
use App\Models\EmployeeSeparationSettlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class EmployeeSeparationSettlementController extends Controller
{
    public function index(SeparationSettlementIndexRequest $request, ListSeparationSettlements $list, ListSeparationWorkspace $workspace): AnonymousResourceCollection|View
    {
        if (! $request->wantsJson()) {
            return view('hr.separation.index', $workspace->execute($request->user(), $request->validated())->toView());
        }

        return EmployeeSeparationSettlementResource::collection($list->execute($request->user(), $request->validated()));
    }

    public function store(StoreSeparationSettlementRequest $request, InitiateSeparationSettlement $initiate): JsonResponse|RedirectResponse
    {
        $settlement = $initiate->execute(new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()->route('hr.separation-settlements.index')->with('status', 'Separation settlement '.$settlement->settlement_number.' initiated.');
        }

        return (new EmployeeSeparationSettlementResource($settlement))
            ->additional(['message' => 'Employee separation settlement initiated.'])
            ->response()
            ->setStatusCode(201);
    }

    public function hrApprove(EmployeeSeparationSettlement $employeeSeparationSettlement, ApproveSeparationSettlementRequest $request, ApproveSeparationSettlementByHr $approve): EmployeeSeparationSettlementResource|RedirectResponse
    {
        $settlement = $approve->execute($employeeSeparationSettlement, new HrCommandData($request->validated(), $request->user(), $request));
        return $request->wantsJson() ? (new EmployeeSeparationSettlementResource($settlement))->additional(['message' => 'Separation settlement HR approved.']) : redirect()->route('hr.separation-settlements.index')->with('status', 'Separation settlement HR approved.');
    }

    public function financeApprove(EmployeeSeparationSettlement $employeeSeparationSettlement, FinanceApproveSeparationSettlementRequest $request, ApproveSeparationSettlementByFinance $approve): EmployeeSeparationSettlementResource|RedirectResponse
    {
        $settlement = $approve->execute($employeeSeparationSettlement, new HrCommandData($request->validated(), $request->user(), $request));
        return $request->wantsJson() ? (new EmployeeSeparationSettlementResource($settlement))->additional(['message' => 'Separation settlement finance approved.']) : redirect()->route('hr.separation-settlements.index')->with('status', 'Separation settlement finance approved.');
    }

    public function complete(EmployeeSeparationSettlement $employeeSeparationSettlement, CompleteSeparationSettlementRequest $request, CompleteSeparationSettlement $complete): EmployeeSeparationSettlementResource|RedirectResponse
    {
        $settlement = $complete->execute($employeeSeparationSettlement, new HrCommandData($request->validated(), $request->user(), $request));
        return $request->wantsJson() ? (new EmployeeSeparationSettlementResource($settlement))->additional(['message' => 'Separation settlement completed.']) : redirect()->route('hr.separation-settlements.index')->with('status', 'Separation settlement completed.');
    }
}
