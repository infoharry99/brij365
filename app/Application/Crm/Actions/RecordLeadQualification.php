<?php

namespace App\Application\Crm\Actions;

use App\Application\Crm\Data\LeadQualificationData;
use App\Application\Scoring\Actions\CalculateAndStoreScore;
use App\Domain\Scoring\Services\ActiveScoringRuleResolver;
use App\Models\Lead;
use App\Models\LeadQualification;
use App\Models\User;
use App\Services\Crm\LeadEngagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class RecordLeadQualification
{
    public function __construct(
        private LeadEngagementService $engagement,
        private ActiveScoringRuleResolver $activeRules,
        private CalculateAndStoreScore $calculateScore,
    ) {}

    public function execute(LeadQualificationData $data, User $actor, Request $request): LeadQualification
    {
        return DB::transaction(function () use ($data, $actor, $request): LeadQualification {
            $qualification = $this->engagement->qualify($data->toArray(), $actor, $request);
            $lead = $qualification->lead()->firstOrFail();

            if ($this->activeRules->resolve((int) $qualification->company_id, 'lead_quality') !== null) {
                $selectedConditions = data_get($qualification->metadata, 'quality_score.selected_conditions', []);
                $this->calculateScore->handle(
                    (int) $qualification->company_id,
                    'lead_quality',
                    Lead::class,
                    (int) $qualification->lead_id,
                    [
                        'budget_fit' => $selectedConditions['budget'] ?? (float) $qualification->budget_score,
                        'decision_authority' => $selectedConditions['authority'] ?? (float) $qualification->authority_score,
                        'requirement_clarity' => $selectedConditions['need'] ?? (float) $qualification->need_score,
                        'purchase_timeline' => $selectedConditions['timeline'] ?? (float) $qualification->timeline_score,
                    ],
                    ['lead_code' => $lead->lead_code, 'qualification_id' => $qualification->id],
                );
            }

            return $qualification;
        });
    }
}
