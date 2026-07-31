<?php

namespace App\Application\Projects\Actions;

use App\Application\Projects\Data\ProjectCostRoiExportData;
use App\Domain\Projects\Services\ProjectCostRoiReportService;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;

final readonly class ExportProjectCostRoi
{
    public function __construct(
        private ProjectCostRoiReportService $reports,
        private AuditLogger $auditLogger,
    ) {}

    /** @param array<string, mixed> $filters */
    public function execute(User $actor, array $filters, Request $request): ProjectCostRoiExportData
    {
        $export = $this->reports->build($actor, $filters);

        $this->auditLogger->record(
            $actor,
            'projects.cost_roi.exported',
            'Exported project cost and ROI report',
            null,
            [
                'format' => 'csv',
                'row_count' => $export->rowCount,
                'filters' => $filters,
                'max_rows' => $this->reports->maximumRows(),
            ],
            $request,
        );

        return $export;
    }
}
