<?php

namespace App\Http\Controllers\Payroll;

use App\Application\Payroll\Actions\ApproveCommissionRun;
use App\Application\Payroll\Actions\CreateCommissionRule;
use App\Application\Payroll\Actions\GenerateCommissionRun;
use App\Application\Payroll\Actions\ListCommissionRules;
use App\Application\Payroll\Actions\ListCommissionRuns;
use App\Application\Payroll\Actions\ListPayrollWorkspace;
use App\Application\Payroll\Actions\RejectCommissionRun;
use App\Application\Payroll\Data\PayrollCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\ApproveCommissionRunRequest;
use App\Http\Requests\Payroll\CommissionRuleIndexRequest;
use App\Http\Requests\Payroll\CommissionRunIndexRequest;
use App\Http\Requests\Payroll\RejectCommissionRunRequest;
use App\Http\Requests\Payroll\StoreCommissionRuleRequest;
use App\Http\Requests\Payroll\StoreCommissionRunRequest;
use App\Http\Resources\CommissionRuleResource;
use App\Http\Resources\CommissionRunResource;
use App\Models\CommissionRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class CommissionController extends Controller
{
    public function rules(CommissionRuleIndexRequest $request, ListCommissionRules $list, ListPayrollWorkspace $workspace): AnonymousResourceCollection|View
    {
        $validated = $request->validated();

        $rules = $list->execute($request->user(), $validated);

        if (! $request->wantsJson()) {
            return view('payroll.workspace.index', $workspace->execute(
                $request->user(),
                'commission_rules',
                filters: $validated,
                commissionRules: $rules,
            )->toView());
        }

        return CommissionRuleResource::collection($rules);
    }

    public function storeRule(StoreCommissionRuleRequest $request, CreateCommissionRule $create): JsonResponse|RedirectResponse
    {
        $rule = $create->execute(new PayrollCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('payroll.commission-rules.index')
                ->with('status', 'Commission rule '.$rule->rule_code.' created.');
        }

        return (new CommissionRuleResource($rule))
            ->response()
            ->setStatusCode(201);
    }

    public function runs(CommissionRunIndexRequest $request, ListCommissionRuns $list, ListPayrollWorkspace $workspace): AnonymousResourceCollection|View
    {
        $validated = $request->validated();

        $runs = $list->execute($request->user(), $validated);

        if (! $request->wantsJson()) {
            return view('payroll.workspace.index', $workspace->execute(
                $request->user(),
                'commission_runs',
                filters: $validated,
                commissionRuns: $runs,
            )->toView());
        }

        return CommissionRunResource::collection($runs);
    }

    public function storeRun(StoreCommissionRunRequest $request, GenerateCommissionRun $generate): JsonResponse|RedirectResponse
    {
        $run = $generate->execute(new PayrollCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('payroll.commission-runs.index')
                ->with('status', 'Commission run '.$run->run_number.' generated.');
        }

        return (new CommissionRunResource($run))
            ->response()
            ->setStatusCode(201);
    }

    public function approveRun(CommissionRun $commissionRun, ApproveCommissionRunRequest $request, ApproveCommissionRun $approve): CommissionRunResource|RedirectResponse
    {
        $approved = $approve->execute($commissionRun, new PayrollCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('payroll.commission-runs.index')
                ->with('status', 'Commission run '.$approved->run_number.' approved.');
        }

        return new CommissionRunResource($approved);
    }

    public function rejectRun(CommissionRun $commissionRun, RejectCommissionRunRequest $request, RejectCommissionRun $reject): CommissionRunResource|RedirectResponse
    {
        $rejected = $reject->execute($commissionRun, new PayrollCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('payroll.commission-runs.index')
                ->with('status', 'Commission run '.$rejected->run_number.' rejected.');
        }

        return new CommissionRunResource($rejected);
    }
}
