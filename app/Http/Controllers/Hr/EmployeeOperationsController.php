<?php

namespace App\Http\Controllers\Hr;

use App\Application\Hr\Actions\ApproveEmployeeLoan;
use App\Application\Hr\Actions\ApproveExpenseClaim;
use App\Application\Hr\Actions\AssignEmployeeAsset;
use App\Application\Hr\Actions\AssignHrHelpdeskTicket;
use App\Application\Hr\Actions\CloseHrHelpdeskTicket;
use App\Application\Hr\Actions\CreateEmployeeAsset;
use App\Application\Hr\Actions\CreateHrHelpdeskTicket;
use App\Application\Hr\Actions\DisburseEmployeeLoan;
use App\Application\Hr\Actions\ListEmployeeAssets;
use App\Application\Hr\Actions\ListEmployeeLoans;
use App\Application\Hr\Actions\ListEmployeeOperationsWorkspace;
use App\Application\Hr\Actions\ListExpenseClaims;
use App\Application\Hr\Actions\ListHrHelpdeskTickets;
use App\Application\Hr\Actions\PayExpenseClaim;
use App\Application\Hr\Actions\RecoverEmployeeAsset;
use App\Application\Hr\Actions\RejectEmployeeLoan;
use App\Application\Hr\Actions\RejectExpenseClaim;
use App\Application\Hr\Actions\ResolveHrHelpdeskTicket;
use App\Application\Hr\Actions\SubmitEmployeeLoan;
use App\Application\Hr\Actions\SubmitExpenseClaim;
use App\Application\Hr\Data\HrCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ApproveExpenseClaimRequest;
use App\Http\Requests\Hr\ApproveLoanRequest;
use App\Http\Requests\Hr\AssignEmployeeAssetRequest;
use App\Http\Requests\Hr\AssignHelpdeskTicketRequest;
use App\Http\Requests\Hr\CloseHelpdeskTicketRequest;
use App\Http\Requests\Hr\DisburseLoanRequest;
use App\Http\Requests\Hr\EmployeeAssetIndexRequest;
use App\Http\Requests\Hr\ExpenseClaimIndexRequest;
use App\Http\Requests\Hr\HelpdeskTicketIndexRequest;
use App\Http\Requests\Hr\LoanIndexRequest;
use App\Http\Requests\Hr\PayExpenseClaimRequest;
use App\Http\Requests\Hr\RecoverEmployeeAssetRequest;
use App\Http\Requests\Hr\RejectExpenseClaimRequest;
use App\Http\Requests\Hr\RejectLoanRequest;
use App\Http\Requests\Hr\ResolveHelpdeskTicketRequest;
use App\Http\Requests\Hr\StoreEmployeeAssetRequest;
use App\Http\Requests\Hr\StoreExpenseClaimRequest;
use App\Http\Requests\Hr\StoreHelpdeskTicketRequest;
use App\Http\Requests\Hr\StoreLoanRequest;
use App\Http\Resources\EmployeeAssetResource;
use App\Http\Resources\EmployeeLoanResource;
use App\Http\Resources\ExpenseClaimResource;
use App\Http\Resources\HrHelpdeskTicketResource;
use App\Models\EmployeeAsset;
use App\Models\EmployeeLoan;
use App\Models\ExpenseClaim;
use App\Models\HrHelpdeskTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class EmployeeOperationsController extends Controller
{
    public function assets(EmployeeAssetIndexRequest $request, ListEmployeeAssets $list, ListEmployeeOperationsWorkspace $workspace): AnonymousResourceCollection|View
    {
        $assets = $list->execute($request->user(), $request->validated());

        if (! $request->wantsJson()) {
            return view('hr.operations.workspace', $workspace->execute(
                $request->user(),
                'assets',
                assets: $assets->withQueryString(),
                filters: $request->validated(),
            )->toView());
        }

        return EmployeeAssetResource::collection($assets);
    }

    public function storeAsset(StoreEmployeeAssetRequest $request, CreateEmployeeAsset $create): JsonResponse|RedirectResponse
    {
        $asset = $create->execute(new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.assets.index')
                ->with('status', 'Employee asset '.$asset->asset_code.' registered.');
        }

        return (new EmployeeAssetResource($asset))
            ->additional(['message' => 'Employee asset registered.'])
            ->response()
            ->setStatusCode(201);
    }

    public function assignAsset(
        EmployeeAsset $employeeAsset,
        AssignEmployeeAssetRequest $request,
        AssignEmployeeAsset $assign,
    ): EmployeeAssetResource|RedirectResponse {
        $asset = $assign->execute($employeeAsset, new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.assets.index')
                ->with('status', 'Employee asset '.$asset->asset_code.' assigned.');
        }

        return (new EmployeeAssetResource($asset))->additional(['message' => 'Employee asset assigned.']);
    }

    public function recoverAsset(
        EmployeeAsset $employeeAsset,
        RecoverEmployeeAssetRequest $request,
        RecoverEmployeeAsset $recover,
    ): EmployeeAssetResource|RedirectResponse {
        $asset = $recover->execute($employeeAsset, new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.assets.index')
                ->with('status', 'Employee asset '.$asset->asset_code.' recovered.');
        }

        return (new EmployeeAssetResource($asset))->additional(['message' => 'Employee asset recovered.']);
    }

    public function claims(ExpenseClaimIndexRequest $request, ListExpenseClaims $list, ListEmployeeOperationsWorkspace $workspace): AnonymousResourceCollection|View
    {
        $claims = $list->execute($request->user(), $request->validated());

        if (! $request->wantsJson()) {
            return view('hr.operations.workspace', $workspace->execute(
                $request->user(),
                'claims',
                claims: $claims->withQueryString(),
                filters: $request->validated(),
            )->toView());
        }

        return ExpenseClaimResource::collection($claims);
    }

    public function storeClaim(StoreExpenseClaimRequest $request, SubmitExpenseClaim $submit): JsonResponse|RedirectResponse
    {
        $claim = $submit->execute(new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.expense-claims.index')
                ->with('status', 'Expense claim '.$claim->claim_number.' submitted.');
        }

        return (new ExpenseClaimResource($claim))
            ->additional(['message' => 'Expense claim submitted.'])
            ->response()
            ->setStatusCode(201);
    }

    public function approveClaim(
        ExpenseClaim $expenseClaim,
        ApproveExpenseClaimRequest $request,
        ApproveExpenseClaim $approve,
    ): ExpenseClaimResource|RedirectResponse {
        $claim = $approve->execute($expenseClaim, new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.expense-claims.index')
                ->with('status', 'Expense claim '.$claim->claim_number.' approved.');
        }

        return (new ExpenseClaimResource($claim))->additional(['message' => 'Expense claim approved.']);
    }

    public function rejectClaim(
        ExpenseClaim $expenseClaim,
        RejectExpenseClaimRequest $request,
        RejectExpenseClaim $reject,
    ): ExpenseClaimResource|RedirectResponse {
        $claim = $reject->execute($expenseClaim, new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.expense-claims.index')
                ->with('status', 'Expense claim '.$claim->claim_number.' rejected.');
        }

        return (new ExpenseClaimResource($claim))->additional(['message' => 'Expense claim rejected.']);
    }

    public function payClaim(
        ExpenseClaim $expenseClaim,
        PayExpenseClaimRequest $request,
        PayExpenseClaim $pay,
    ): ExpenseClaimResource|RedirectResponse {
        $claim = $pay->execute($expenseClaim, new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.expense-claims.index')
                ->with('status', 'Expense claim '.$claim->claim_number.' marked paid.');
        }

        return (new ExpenseClaimResource($claim))->additional(['message' => 'Expense claim marked as paid.']);
    }

    public function loans(LoanIndexRequest $request, ListEmployeeLoans $list, ListEmployeeOperationsWorkspace $workspace): AnonymousResourceCollection|View
    {
        $loans = $list->execute($request->user(), $request->validated());

        if (! $request->wantsJson()) {
            return view('hr.operations.workspace', $workspace->execute(
                $request->user(),
                'loans',
                loans: $loans->withQueryString(),
                filters: $request->validated(),
            )->toView());
        }

        return EmployeeLoanResource::collection($loans);
    }

    public function storeLoan(StoreLoanRequest $request, SubmitEmployeeLoan $submit): JsonResponse|RedirectResponse
    {
        $loan = $submit->execute(new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.loans.index')
                ->with('status', 'Employee loan '.$loan->loan_number.' submitted.');
        }

        return (new EmployeeLoanResource($loan))->additional(['message' => 'Employee loan request submitted.'])->response()->setStatusCode(201);
    }

    public function approveLoan(EmployeeLoan $employeeLoan, ApproveLoanRequest $request, ApproveEmployeeLoan $approve): EmployeeLoanResource|RedirectResponse
    {
        $loan = $approve->execute($employeeLoan, new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.loans.index')
                ->with('status', 'Employee loan '.$loan->loan_number.' approved.');
        }

        return (new EmployeeLoanResource($loan))
            ->additional(['message' => 'Employee loan approved.']);
    }

    public function rejectLoan(EmployeeLoan $employeeLoan, RejectLoanRequest $request, RejectEmployeeLoan $reject): EmployeeLoanResource|RedirectResponse
    {
        $loan = $reject->execute($employeeLoan, new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.loans.index')
                ->with('status', 'Employee loan '.$loan->loan_number.' rejected.');
        }

        return (new EmployeeLoanResource($loan))
            ->additional(['message' => 'Employee loan rejected.']);
    }

    public function disburseLoan(EmployeeLoan $employeeLoan, DisburseLoanRequest $request, DisburseEmployeeLoan $disburse): EmployeeLoanResource|RedirectResponse
    {
        $loan = $disburse->execute($employeeLoan, new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.loans.index')
                ->with('status', 'Employee loan '.$loan->loan_number.' disbursed.');
        }

        return (new EmployeeLoanResource($loan))
            ->additional(['message' => 'Employee loan disbursed.']);
    }

    public function helpdeskTickets(HelpdeskTicketIndexRequest $request, ListHrHelpdeskTickets $list, ListEmployeeOperationsWorkspace $workspace): AnonymousResourceCollection|View
    {
        $tickets = $list->execute($request->user(), $request->validated());

        if (! $request->wantsJson()) {
            return view('hr.operations.workspace', $workspace->execute(
                $request->user(),
                'helpdesk',
                tickets: $tickets->withQueryString(),
                filters: $request->validated(),
            )->toView());
        }

        return HrHelpdeskTicketResource::collection($tickets);
    }

    public function storeHelpdeskTicket(StoreHelpdeskTicketRequest $request, CreateHrHelpdeskTicket $create): JsonResponse|RedirectResponse
    {
        $ticket = $create->execute(new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.helpdesk-tickets.index')
                ->with('status', 'HR helpdesk ticket '.$ticket->ticket_number.' created.');
        }

        return (new HrHelpdeskTicketResource($ticket))->additional(['message' => 'HR helpdesk ticket created.'])->response()->setStatusCode(201);
    }

    public function assignHelpdeskTicket(HrHelpdeskTicket $hrHelpdeskTicket, AssignHelpdeskTicketRequest $request, AssignHrHelpdeskTicket $assign): HrHelpdeskTicketResource|RedirectResponse
    {
        $ticket = $assign->execute($hrHelpdeskTicket, new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.helpdesk-tickets.index')
                ->with('status', 'HR helpdesk ticket '.$ticket->ticket_number.' assigned.');
        }

        return (new HrHelpdeskTicketResource($ticket))
            ->additional(['message' => 'HR helpdesk ticket assigned.']);
    }

    public function resolveHelpdeskTicket(HrHelpdeskTicket $hrHelpdeskTicket, ResolveHelpdeskTicketRequest $request, ResolveHrHelpdeskTicket $resolve): HrHelpdeskTicketResource|RedirectResponse
    {
        $ticket = $resolve->execute($hrHelpdeskTicket, new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.helpdesk-tickets.index')
                ->with('status', 'HR helpdesk ticket '.$ticket->ticket_number.' resolved.');
        }

        return (new HrHelpdeskTicketResource($ticket))
            ->additional(['message' => 'HR helpdesk ticket resolved.']);
    }

    public function closeHelpdeskTicket(HrHelpdeskTicket $hrHelpdeskTicket, CloseHelpdeskTicketRequest $request, CloseHrHelpdeskTicket $close): HrHelpdeskTicketResource|RedirectResponse
    {
        $ticket = $close->execute($hrHelpdeskTicket, new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.helpdesk-tickets.index')
                ->with('status', 'HR helpdesk ticket '.$ticket->ticket_number.' closed.');
        }

        return (new HrHelpdeskTicketResource($ticket))
            ->additional(['message' => 'HR helpdesk ticket closed.']);
    }
}
