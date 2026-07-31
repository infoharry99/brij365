<?php

namespace App\Application\Recruitment\Actions;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Recruitment\RecruitmentReportService;
use Illuminate\Http\Request;

final class ViewRecruitmentSourceSummary
{
    public function __construct(private readonly RecruitmentReportService $reports, private readonly AuditLogger $audit) {}

    public function execute(User $actor, array $filters, Request $request): array
    {
        $summary = $this->reports->sourceSummary($actor, $filters);
        $this->audit->record($actor, 'recruitment.source_summary.viewed', 'Viewed recruitment source summary', null, ['company_id' => $summary['scope']['company_id'] ?? null, 'source_count' => $summary['totals']['sources'] ?? 0, 'candidate_count' => $summary['totals']['candidates'] ?? 0, 'filters' => $summary['filters'] ?? []], $request);

        return $summary;
    }
}
