<?php

namespace App\Application\Collaboration\Actions;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Collaboration\CollaborationService;
use App\Services\Governance\ManagementReportService;
use App\Services\Governance\ReportLimitPolicy;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ExportMailboxRegister
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
        unset($filters['format']);
        $rows = $this->collaboration->messageExportRows($user, $filters, $this->limits->maxExportRows());
        $messageNumber = count($rows) === 1 && isset($filters['message_id']) ? (string) ($rows[0]['message_number'] ?? $filters['message_id']) : null;
        $baseFilename = $messageNumber ? 'builder360-mailbox-message-'.$messageNumber : 'builder360-collaboration-messages';
        $title = $messageNumber ? 'Builder360 Mailbox Message '.$messageNumber : 'Builder360 Collaboration Message Register';

        $this->audit->record($user, 'collaboration.message.exported', 'Exported collaboration message register', null, [
            'format' => $format,
            'row_count' => count($rows),
            'filters' => collect($filters)->only(['folder', 'status', 'priority', 'project_id', 'thread_key', 'q', 'message_id'])->all(),
        ], $request);

        if ($format === 'pdf') {
            return response($this->reports->pdf($rows, $title), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$baseFilename.'.pdf"',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response($this->reports->csv($rows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$baseFilename.'.csv"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
