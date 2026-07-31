<?php

namespace App\Application\Collaboration\Actions;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Collaboration\CollaborationService;
use App\Services\Governance\ManagementReportService;
use App\Services\Governance\ReportLimitPolicy;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ExportWorkTaskRegister
{
    public function __construct(
        private readonly CollaborationService $collaboration,
        private readonly ManagementReportService $reports,
        private readonly ReportLimitPolicy $limits,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string,mixed> $filters */
    public function execute(User $user, array $filters, Request $request): Response
    {
        $format = $filters['format'] ?? 'csv';
        $rows = $this->collaboration->taskExportRows($user, $filters, $this->limits->maxExportRows());

        $this->audit->record($user, 'collaboration.task.exported', 'Exported collaboration task register', null, [
            'format' => $format,
            'row_count' => count($rows),
            'filters' => collect($filters)->only(['status', 'priority', 'assigned_to_user_id', 'project_id', 'module_context', 'due_from', 'due_to', 'q'])->all(),
        ], $request);

        if ($format === 'pdf') {
            return response($this->reports->pdf($rows, 'Builder360 Collaboration Task Register'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="builder360-collaboration-tasks.pdf"',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response($this->reports->csv($rows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="builder360-collaboration-tasks.csv"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
