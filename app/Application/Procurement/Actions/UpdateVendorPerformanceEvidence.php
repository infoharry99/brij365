<?php

namespace App\Application\Procurement\Actions;

use App\Application\Procurement\Data\VendorPerformanceEvidenceData;
use App\Domain\Procurement\Services\VendorPerformanceScoringService;
use App\Models\ScoreSnapshot;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;

final readonly class UpdateVendorPerformanceEvidence
{
    public function __construct(
        private VendorPerformanceScoringService $scoring,
        private AuditLogger $auditLogger,
    ) {}

    public function execute(Vendor $vendor, VendorPerformanceEvidenceData $evidence, User $actor, Request $request): ScoreSnapshot
    {
        $before = $vendor->scoring_inputs;
        $snapshot = $this->scoring->updateAndCalculate($vendor, $evidence);

        $this->auditLogger->record($actor, 'procurement.vendor.performance_scored', 'Updated vendor performance evidence', $vendor, [
            'before' => $before,
            'after' => $evidence->toArray(),
            'score_snapshot_id' => $snapshot->id,
            'score' => (float) $snapshot->total_score,
            'band' => $snapshot->score_band,
            'rule_version' => $snapshot->rule_version,
        ], $request);

        return $snapshot;
    }
}
