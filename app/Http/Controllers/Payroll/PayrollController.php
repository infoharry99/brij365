<?php

namespace App\Http\Controllers\Payroll;

use App\Application\Payroll\Actions\ApprovePayrollRun;
use App\Application\Payroll\Actions\GeneratePayrollRun;
use App\Application\Payroll\Actions\ListPayrollBankBatches;
use App\Application\Payroll\Actions\ListPayrollComponents;
use App\Application\Payroll\Actions\ListPayrollRuns;
use App\Application\Payroll\Actions\ListPayrollWorkspace;
use App\Application\Payroll\Actions\ListSalaryStructures;
use App\Application\Payroll\Actions\PreparePayrollBankBatch;
use App\Application\Payroll\Actions\ReleasePayrollBankBatch;
use App\Application\Payroll\Data\PayrollCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\ApprovePayrollRunRequest;
use App\Http\Requests\Payroll\GeneratePayrollRunRequest;
use App\Http\Requests\Payroll\PayrollBankTransferBatchIndexRequest;
use App\Http\Requests\Payroll\PayrollComponentIndexRequest;
use App\Http\Requests\Payroll\PayrollRunIndexRequest;
use App\Http\Requests\Payroll\PreparePayrollBankTransferBatchRequest;
use App\Http\Requests\Payroll\ReleasePayrollBankTransferBatchRequest;
use App\Http\Requests\Payroll\SalaryStructureIndexRequest;
use App\Http\Resources\PayrollBankTransferBatchResource;
use App\Http\Resources\PayrollComponentResource;
use App\Http\Resources\PayrollRunResource;
use App\Http\Resources\SalaryStructureResource;
use App\Models\PayrollBankTransferBatch;
use App\Models\PayrollRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function components(PayrollComponentIndexRequest $request, ListPayrollComponents $list, ListPayrollWorkspace $workspace): AnonymousResourceCollection|View
    {
        $validated = $request->validated();

        $components = $list->execute($request->user(), $validated);

        if (! $request->wantsJson()) {
            return view('payroll.workspace.index', $workspace->execute($request->user(), 'components', filters: $validated, components: $components)->toView());
        }

        return PayrollComponentResource::collection($components);
    }

    public function structures(SalaryStructureIndexRequest $request, ListSalaryStructures $list, ListPayrollWorkspace $workspace): AnonymousResourceCollection|View
    {
        $validated = $request->validated();

        $structures = $list->execute($request->user(), $validated);

        if (! $request->wantsJson()) {
            return view('payroll.workspace.index', $workspace->execute($request->user(), 'structures', filters: $validated, structures: $structures)->toView());
        }

        return SalaryStructureResource::collection($structures);
    }

    public function runs(PayrollRunIndexRequest $request, ListPayrollRuns $list, ListPayrollWorkspace $workspace): AnonymousResourceCollection|View
    {
        $validated = $request->validated();

        $runs = $list->execute($request->user(), $validated);

        if (! $request->wantsJson()) {
            return view('payroll.workspace.index', $workspace->execute($request->user(), 'runs', filters: $validated, runs: $runs)->toView());
        }

        return PayrollRunResource::collection($runs);
    }

    public function generate(GeneratePayrollRunRequest $request, GeneratePayrollRun $generate): JsonResponse|RedirectResponse
    {
        $run = $generate->execute(new PayrollCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('payroll.runs.index')
                ->with('status', 'Payroll run '.$run->run_number.' generated.');
        }

        return (new PayrollRunResource($run))
            ->response()
            ->setStatusCode(201);
    }

    public function approve(ApprovePayrollRunRequest $request, PayrollRun $payrollRun, ApprovePayrollRun $approve): PayrollRunResource|RedirectResponse
    {
        $approved = $approve->execute($payrollRun, new PayrollCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('payroll.runs.index')
                ->with('status', 'Payroll run '.$approved->run_number.' approved.');
        }

        return new PayrollRunResource($approved);
    }

    public function bankTransferBatches(
        PayrollBankTransferBatchIndexRequest $request,
        ListPayrollBankBatches $list,
        ListPayrollWorkspace $workspace,
    ): AnonymousResourceCollection|View {
        $validated = $request->validated();

        $batches = $list->execute($request->user(), $validated);

        if (! $request->wantsJson()) {
            return view('payroll.workspace.index', $workspace->execute($request->user(), 'bank_batches', filters: $validated, batches: $batches)->toView());
        }

        return PayrollBankTransferBatchResource::collection($batches);
    }

    public function prepareBankTransferBatch(
        PreparePayrollBankTransferBatchRequest $request,
        PayrollRun $payrollRun,
        PreparePayrollBankBatch $prepare,
    ): JsonResponse|RedirectResponse {
        $batch = $prepare->execute($payrollRun, new PayrollCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('payroll.bank-transfer-batches.index')
                ->with('status', 'Bank transfer batch '.$batch->batch_number.' prepared.');
        }

        return (new PayrollBankTransferBatchResource($batch))
            ->response()
            ->setStatusCode(201);
    }

    public function releaseBankTransferBatch(
        ReleasePayrollBankTransferBatchRequest $request,
        PayrollBankTransferBatch $payrollBankTransferBatch,
        ReleasePayrollBankBatch $release,
    ): PayrollBankTransferBatchResource|RedirectResponse {
        $released = $release->execute($payrollBankTransferBatch, new PayrollCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('payroll.bank-transfer-batches.index')
                ->with('status', 'Bank transfer batch '.$released->batch_number.' released.');
        }

        return new PayrollBankTransferBatchResource($released);
    }
}
