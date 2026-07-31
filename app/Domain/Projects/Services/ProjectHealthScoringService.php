<?php

namespace App\Domain\Projects\Services;

use App\Application\Projects\Data\ProjectHealthEvidenceData;
use App\Application\Scoring\Actions\CalculateAndStoreScore;
use App\Models\Project;
use App\Models\ScoreSnapshot;
use Illuminate\Support\Facades\DB;

final readonly class ProjectHealthScoringService
{
    public function __construct(private CalculateAndStoreScore $calculateScore) {}

    public function updateAndCalculate(Project $project, ProjectHealthEvidenceData $evidence): ScoreSnapshot
    {
        return DB::transaction(function () use ($project, $evidence): ScoreSnapshot {
            $locked = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $inputs = $evidence->toArray();
            $locked->forceFill(['scoring_inputs' => $inputs])->save();

            return $this->calculateScore->handle(
                (int) $locked->company_id,
                'project_health',
                Project::class,
                (int) $locked->id,
                $inputs,
                ['source' => 'project_health_evidence'],
            );
        });
    }
}
