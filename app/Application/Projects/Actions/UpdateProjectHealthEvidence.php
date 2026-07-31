<?php

namespace App\Application\Projects\Actions;

use App\Application\Projects\Data\ProjectHealthEvidenceData;
use App\Domain\Projects\Services\ProjectHealthScoringService;
use App\Models\Project;
use App\Models\ScoreSnapshot;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;

final readonly class UpdateProjectHealthEvidence
{
    public function __construct(
        private ProjectHealthScoringService $scoring,
        private AuditLogger $auditLogger,
    ) {}

    public function execute(Project $project, ProjectHealthEvidenceData $evidence, User $actor, Request $request): ScoreSnapshot
    {
        $before = $project->scoring_inputs;
        $snapshot = $this->scoring->updateAndCalculate($project, $evidence);

        $this->auditLogger->record($actor, 'projects.health_score.updated', 'Updated project health evidence', $project, [
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
