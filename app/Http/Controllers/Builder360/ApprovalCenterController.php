<?php

namespace App\Http\Controllers\Builder360;

use App\Application\Approvals\Actions\ListApprovalCenter;
use App\Application\Approvals\Actions\ExportApprovalCenter;
use App\Application\Approvals\Data\ApprovalCenterContextData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Builder360\ApprovalCenterIndexRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApprovalCenterController extends Controller
{
    public function index(
        ApprovalCenterIndexRequest $request,
        ListApprovalCenter $action,
    ): JsonResponse|View
    {
        $filters = $request->validated();
        $roleSlug = (string) $request->session()->get('builder360.selected_role_slug', $request->user()?->role?->slug);
        $projectId = $filters['project_id'] ?? $request->session()->get('builder360.selected_project_id');

        $page = $action->execute($request->user(), new ApprovalCenterContextData(
            $roleSlug,
            $projectId ? (int) $projectId : null,
            $filters,
        ));

        if (! $request->wantsJson()) {
            return view('builder360.classic.approvals.index', [
                'approvalPayload' => $page->payload,
                'approvalFilters' => $page->filters,
            ]);
        }

        return response()->json($page->payload);
    }

    public function export(ApprovalCenterIndexRequest $request, ExportApprovalCenter $export): StreamedResponse|JsonResponse
    {
        $filters = $request->validated();
        $roleSlug = (string) $request->session()->get('builder360.selected_role_slug', $request->user()?->role?->slug);
        $projectId = $filters['project_id'] ?? $request->session()->get('builder360.selected_project_id');
        $file = $export->execute($request->user(), new ApprovalCenterContextData($roleSlug, $projectId ? (int) $projectId : null, $filters), $request);

        return response()->streamDownload(function () use ($file): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Number', 'Module', 'Type', 'Project', 'Description', 'Raised By', 'Amount', 'Priority', 'Status', 'Age']);
            foreach ($file->rows as $row) {
                fputcsv($handle, [
                    $row['number'] ?? '',
                    $row['source_module'] ?? '',
                    $row['type'] ?? '',
                    $row['project_label'] ?? '',
                    $row['description'] ?? '',
                    $row['raised_by'] ?? '',
                    $row['amount_display'] ?? '',
                    $row['priority'] ?? '',
                    $row['status'] ?? '',
                    $row['age'] ?? '',
                ]);
            }
            fclose($handle);
        }, $file->filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

}
