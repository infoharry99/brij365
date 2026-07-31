<?php

namespace App\Http\Controllers\Governance;

use App\Application\Governance\Actions\GenerateReportRegister;
use App\Application\Governance\Actions\RenderReportExport;
use App\Application\Governance\Actions\ViewManagementSummary;
use App\Application\Governance\Actions\ArchiveReportSchedule;
use App\Application\Governance\Actions\PinReport;
use App\Application\Governance\Actions\ScheduleReport;
use App\Application\Governance\Actions\UnpinReport;
use App\Application\Governance\Data\GovernanceCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Governance\ArchiveReportScheduleRequest;
use App\Http\Requests\Governance\DestroyReportPinRequest;
use App\Http\Requests\Governance\ManagementSummaryRequest;
use App\Http\Requests\Governance\ReportRegisterRequest;
use App\Http\Requests\Governance\StoreReportPinRequest;
use App\Http\Requests\Governance\StoreReportScheduleRequest;
use App\Models\ReportPin;
use App\Models\ReportSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ManagementReportController extends Controller
{
    public function summary(
        ManagementSummaryRequest $request,
        ViewManagementSummary $action,
    ): JsonResponse|Response
    {
        $format = strtolower((string) ($request->validated('format') ?? 'json'));
        $page = $action->execute($request->user(), $format, $request);

        if ($format === 'csv') {
            return response($page->csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="builder360-management-summary.csv"',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response()->json([
            'data' => $page->summary,
        ]);
    }

    public function register(
        ReportRegisterRequest $request,
        GenerateReportRegister $action,
        RenderReportExport $render,
    ): JsonResponse|Response|View
    {
        $page = $action->execute($request->user(), $request->validated(), $request);

        if (in_array($page->format, ['csv', 'excel', 'pdf'], true)) {
            $export = $render->execute($page);

            return response($export->body, 200, [
                'Content-Type' => $export->contentType,
                'Content-Disposition' => 'attachment; filename="builder360-'.$page->report.'-report.'.$export->extension.'"',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        if (! $request->wantsJson()) {
            return view('governance.reports.index', ['page' => $page]);
        }

        return response()->json([
            'data' => [
                'report' => $page->report,
                'row_count' => count($page->rows),
                'rows' => $page->rows,
            ],
        ]);
    }

    public function storePin(StoreReportPinRequest $request, PinReport $action): JsonResponse
    {
        $pin = $action->execute(new GovernanceCommandData($request->validated(), $request->user(), $request));

        return response()->json([
            'data' => $this->pinPayload($pin),
            'message' => 'Report pinned.',
        ], 201);
    }

    public function destroyPin(ReportPin $reportPin, DestroyReportPinRequest $request, UnpinReport $action): JsonResponse
    {
        $action->execute($reportPin, new GovernanceCommandData([], $request->user(), $request));

        return response()->json(['message' => 'Report unpinned.']);
    }

    public function storeSchedule(StoreReportScheduleRequest $request, ScheduleReport $action): JsonResponse
    {
        $schedule = $action->execute(new GovernanceCommandData($request->validated(), $request->user(), $request));

        return response()->json([
            'data' => $this->schedulePayload($schedule),
            'message' => 'Report schedule created.',
        ], 201);
    }

    public function archiveSchedule(ReportSchedule $reportSchedule, ArchiveReportScheduleRequest $request, ArchiveReportSchedule $action): JsonResponse
    {
        $archived = $action->execute($reportSchedule, new GovernanceCommandData([], $request->user(), $request));

        return response()->json([
            'data' => $this->schedulePayload($archived),
            'message' => 'Report schedule archived.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function pinPayload(ReportPin $pin): array
    {
        return [
            'id' => $pin->id,
            'report_key' => $pin->report_key,
            'label' => $pin->label,
            'filters' => $pin->filters ?? [],
            'created_at' => $pin->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function schedulePayload(ReportSchedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'report_key' => $schedule->report_key,
            'label' => $schedule->label,
            'frequency' => $schedule->frequency,
            'format' => $schedule->format,
            'filters' => $schedule->filters ?? [],
            'recipients' => $schedule->recipients ?? [],
            'starts_on' => $schedule->starts_on?->toDateString(),
            'ends_on' => $schedule->ends_on?->toDateString(),
            'next_run_at' => $schedule->next_run_at?->toISOString(),
            'status' => $schedule->status,
        ];
    }
}
