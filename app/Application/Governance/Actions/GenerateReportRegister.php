<?php

namespace App\Application\Governance\Actions;

use App\Application\Governance\Data\ReportRegisterData;
use App\Domain\Governance\Services\ReportRegisterCatalog;
use App\Models\Project;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Governance\ManagementReportService;
use App\Services\Security\CompanyScopeService;
use Illuminate\Http\Request;

final class GenerateReportRegister
{
    public function __construct(
        private readonly ManagementReportService $reports,
        private readonly ReportRegisterCatalog $catalog,
        private readonly CompanyScopeService $companyScope,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $filters */
    public function execute(User $actor, array $filters, ?Request $request = null): ReportRegisterData
    {
        $report = (string) ($filters['report'] ?? 'bookings');
        $format = $this->normalizeFormat($filters['format'] ?? 'json');
        $rows = $this->reports->register($actor, $filters);
        $projects = Project::query()->where('status', 'active')->orderBy('code');
        $this->companyScope->apply($projects, $actor);

        $this->audit->record(
            $actor,
            in_array($format, ['csv', 'excel', 'pdf'], true) ? 'governance.report.exported' : 'governance.report.generated',
            in_array($format, ['csv', 'excel', 'pdf'], true) ? 'Exported governance report register' : 'Generated governance report register',
            null,
            [
                'report' => $report,
                'format' => $format,
                'row_count' => count($rows),
                'filters' => collect($filters)->only(['report', 'format', 'status', 'project_id', 'date_from', 'date_to'])->all(),
            ],
            $request,
        );

        return new ReportRegisterData(
            report: $report,
            format: $format,
            rows: $rows,
            filters: $filters,
            reports: $this->catalog->reports($actor->can('audit.view')),
            projects: $projects->get(['id', 'code', 'name'])->map(static fn (Project $project): array => [
                'id' => $project->id, 'code' => $project->code, 'name' => $project->name,
            ])->all(),
        );
    }

    private function normalizeFormat(mixed $format): string
    {
        return match (strtolower((string) $format)) {
            'xls' => 'excel',
            'csv', 'excel', 'pdf' => strtolower((string) $format),
            default => 'json',
        };
    }
}
