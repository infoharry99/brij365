<?php

namespace App\Http\Controllers\Construction;

use App\Application\Construction\Actions\ApproveContractorBill;
use App\Application\Construction\Actions\ApproveContractorMeasurement;
use App\Application\Construction\Actions\ApproveDailyProgressReport;
use App\Application\Construction\Actions\CreateBoqItem;
use App\Application\Construction\Actions\CreateConstructionMilestone;
use App\Application\Construction\Actions\CreateContractorBill;
use App\Application\Construction\Actions\ListBoqItems;
use App\Application\Construction\Actions\ListConstructionMilestones;
use App\Application\Construction\Actions\ListConstructionProgressWorkspace;
use App\Application\Construction\Actions\ListContractorBills;
use App\Application\Construction\Actions\ListContractorMeasurements;
use App\Application\Construction\Actions\ListDailyProgressReports;
use App\Application\Construction\Actions\MarkContractorBillPaid;
use App\Application\Construction\Actions\RejectContractorMeasurement;
use App\Application\Construction\Actions\RejectDailyProgressReport;
use App\Application\Construction\Actions\SubmitContractorMeasurement;
use App\Application\Construction\Actions\SubmitDailyProgressReport;
use App\Application\Construction\Data\ConstructionCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Construction\ApproveContractorBillRequest;
use App\Http\Requests\Construction\ApproveContractorMeasurementRequest;
use App\Http\Requests\Construction\ApproveDailyProgressReportRequest;
use App\Http\Requests\Construction\BoqItemIndexRequest;
use App\Http\Requests\Construction\ConstructionMilestoneIndexRequest;
use App\Http\Requests\Construction\ContractorBillIndexRequest;
use App\Http\Requests\Construction\ContractorMeasurementIndexRequest;
use App\Http\Requests\Construction\DailyProgressReportIndexRequest;
use App\Http\Requests\Construction\MarkContractorBillPaidRequest;
use App\Http\Requests\Construction\RejectContractorMeasurementRequest;
use App\Http\Requests\Construction\RejectDailyProgressReportRequest;
use App\Http\Requests\Construction\StoreBoqItemRequest;
use App\Http\Requests\Construction\StoreConstructionMilestoneRequest;
use App\Http\Requests\Construction\StoreContractorBillRequest;
use App\Http\Requests\Construction\StoreContractorMeasurementRequest;
use App\Http\Requests\Construction\StoreDailyProgressReportRequest;
use App\Http\Resources\BoqItemResource;
use App\Http\Resources\ConstructionMilestoneResource;
use App\Http\Resources\ContractorBillResource;
use App\Http\Resources\ContractorMeasurementResource;
use App\Http\Resources\DailyProgressReportResource;
use App\Models\ContractorBill;
use App\Models\ContractorMeasurement;
use App\Models\DailyProgressReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class ConstructionController extends Controller
{
    public function milestones(ConstructionMilestoneIndexRequest $request, ListConstructionMilestones $list, ListConstructionProgressWorkspace $workspace): AnonymousResourceCollection|View
    {
        $validated = $request->validated();

        $milestones = $list->execute($request->user(), $validated);

        if (! $request->wantsJson()) {
            return view('construction.progress.index', $workspace->execute($request->user(), $validated, 'milestones', $milestones)->toView());
        }

        return ConstructionMilestoneResource::collection($milestones);
    }

    public function storeMilestone(StoreConstructionMilestoneRequest $request, CreateConstructionMilestone $create): ConstructionMilestoneResource|RedirectResponse
    {
        $milestone = $create->execute(new ConstructionCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('construction.milestones.index', ['project_id' => $milestone->project_id])
                ->with('status', "Construction milestone {$milestone->milestone_code} created.");
        }

        return (new ConstructionMilestoneResource($milestone))->additional(['message' => 'Construction milestone created.']);
    }

    public function boqItems(BoqItemIndexRequest $request, ListBoqItems $list): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $items = $list->execute($request->user(), $validated);

        return BoqItemResource::collection($items);
    }

    public function storeBoqItem(StoreBoqItemRequest $request, CreateBoqItem $create): BoqItemResource
    {
        $item = $create->execute(new ConstructionCommandData($request->validated(), $request->user(), $request));

        return (new BoqItemResource($item))->additional(['message' => 'BOQ item created.']);
    }

    public function dailyReports(DailyProgressReportIndexRequest $request, ListDailyProgressReports $list, ListConstructionProgressWorkspace $workspace): AnonymousResourceCollection|View
    {
        $validated = $request->validated();

        $reports = $list->execute($request->user(), $validated);

        if (! $request->wantsJson()) {
            return view('construction.progress.index', $workspace->execute($request->user(), $validated, 'daily_reports', null, $reports)->toView());
        }

        return DailyProgressReportResource::collection($reports);
    }

    public function storeDailyReport(StoreDailyProgressReportRequest $request, SubmitDailyProgressReport $submit): DailyProgressReportResource|RedirectResponse
    {
        $report = $submit->execute(new ConstructionCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('construction.daily-progress-reports.index', ['project_id' => $report->project_id, 'status' => 'submitted'])
                ->with('status', "Daily progress report {$report->report_number} submitted for approval.");
        }

        return (new DailyProgressReportResource($report))->additional(['message' => 'Daily progress report submitted.']);
    }

    public function approveDailyReport(
        DailyProgressReport $dailyProgressReport,
        ApproveDailyProgressReportRequest $request,
        ApproveDailyProgressReport $approve,
    ): DailyProgressReportResource|RedirectResponse {
        $report = $approve->execute($dailyProgressReport, new ConstructionCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('construction.daily-progress-reports.index', ['project_id' => $report->project_id, 'status' => 'approved'])
                ->with('status', "Daily progress report {$report->report_number} approved.");
        }

        return (new DailyProgressReportResource($report))->additional(['message' => 'Daily progress report approved.']);
    }

    public function rejectDailyReport(
        DailyProgressReport $dailyProgressReport,
        RejectDailyProgressReportRequest $request,
        RejectDailyProgressReport $reject,
    ): DailyProgressReportResource|RedirectResponse {
        $report = $reject->execute($dailyProgressReport, new ConstructionCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('construction.daily-progress-reports.index', ['project_id' => $report->project_id, 'status' => 'rejected'])
                ->with('status', "Daily progress report {$report->report_number} rejected.");
        }

        return (new DailyProgressReportResource($report))->additional(['message' => 'Daily progress report rejected.']);
    }

    public function contractorMeasurements(ContractorMeasurementIndexRequest $request, ListContractorMeasurements $list): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $measurements = $list->execute($request->user(), $validated);

        return ContractorMeasurementResource::collection($measurements);
    }

    public function storeContractorMeasurement(StoreContractorMeasurementRequest $request, SubmitContractorMeasurement $submit): ContractorMeasurementResource
    {
        $measurement = $submit->execute(new ConstructionCommandData($request->validated(), $request->user(), $request));

        return (new ContractorMeasurementResource($measurement))->additional(['message' => 'Contractor measurement submitted.']);
    }

    public function approveContractorMeasurement(
        ContractorMeasurement $contractorMeasurement,
        ApproveContractorMeasurementRequest $request,
        ApproveContractorMeasurement $approve,
    ): ContractorMeasurementResource {
        $measurement = $approve->execute($contractorMeasurement, new ConstructionCommandData($request->validated(), $request->user(), $request));

        return (new ContractorMeasurementResource($measurement))->additional(['message' => 'Contractor measurement approved.']);
    }

    public function rejectContractorMeasurement(
        ContractorMeasurement $contractorMeasurement,
        RejectContractorMeasurementRequest $request,
        RejectContractorMeasurement $reject,
    ): ContractorMeasurementResource {
        $measurement = $reject->execute($contractorMeasurement, new ConstructionCommandData($request->validated(), $request->user(), $request));

        return (new ContractorMeasurementResource($measurement))->additional(['message' => 'Contractor measurement rejected.']);
    }

    public function contractorBills(ContractorBillIndexRequest $request, ListContractorBills $list): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $bills = $list->execute($request->user(), $validated);

        return ContractorBillResource::collection($bills);
    }

    public function storeContractorBill(StoreContractorBillRequest $request, CreateContractorBill $create): ContractorBillResource
    {
        $bill = $create->execute(new ConstructionCommandData($request->validated(), $request->user(), $request));

        return (new ContractorBillResource($bill))->additional(['message' => 'Contractor bill submitted.']);
    }

    public function approveContractorBill(
        ContractorBill $contractorBill,
        ApproveContractorBillRequest $request,
        ApproveContractorBill $approve,
    ): ContractorBillResource {
        $bill = $approve->execute($contractorBill, new ConstructionCommandData($request->validated(), $request->user(), $request));

        return (new ContractorBillResource($bill))->additional(['message' => 'Contractor bill approved.']);
    }

    public function markContractorBillPaid(
        ContractorBill $contractorBill,
        MarkContractorBillPaidRequest $request,
        MarkContractorBillPaid $markPaid,
    ): ContractorBillResource {
        $bill = $markPaid->execute($contractorBill, new ConstructionCommandData($request->validated(), $request->user(), $request));

        return (new ContractorBillResource($bill))->additional(['message' => 'Contractor bill payment recorded.']);
    }
}
