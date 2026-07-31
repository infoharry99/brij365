<?php

namespace App\Application\Hr\Data;

final readonly class ExitInterviewSubmissionData
{
    /**
     * @param  array<int|string, mixed>  $confidentialResponses
     * @param  array<int, string>  $riskFlags
     * @param  array<string, float>  $scoringInputs
     */
    public function __construct(
        public string $separationReason,
        public string $rehireRecommendation,
        public int $overallExperienceRating,
        public ?int $managerRelationshipRating,
        public ?int $workloadRating,
        public ?int $compensationRating,
        public ?string $publicFeedback,
        public ?string $improvementSuggestions,
        public array $confidentialResponses,
        public array $riskFlags,
        public array $scoringInputs,
    ) {}

    /** @param array<string, mixed> $data */
    public static function from(array $data): self
    {
        return new self(
            separationReason: (string) $data['separation_reason'],
            rehireRecommendation: (string) $data['rehire_recommendation'],
            overallExperienceRating: (int) $data['overall_experience_rating'],
            managerRelationshipRating: isset($data['manager_relationship_rating']) ? (int) $data['manager_relationship_rating'] : null,
            workloadRating: isset($data['workload_rating']) ? (int) $data['workload_rating'] : null,
            compensationRating: isset($data['compensation_rating']) ? (int) $data['compensation_rating'] : null,
            publicFeedback: $data['public_feedback'] ?? null,
            improvementSuggestions: $data['improvement_suggestions'] ?? null,
            confidentialResponses: $data['confidential_responses'],
            riskFlags: $data['risk_flags'] ?? [],
            scoringInputs: collect($data['scoring_inputs'] ?? [])->map(
                static fn (mixed $score): float => (float) $score,
            )->all(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'separation_reason' => $this->separationReason,
            'rehire_recommendation' => $this->rehireRecommendation,
            'overall_experience_rating' => $this->overallExperienceRating,
            'manager_relationship_rating' => $this->managerRelationshipRating,
            'workload_rating' => $this->workloadRating,
            'compensation_rating' => $this->compensationRating,
            'public_feedback' => $this->publicFeedback,
            'improvement_suggestions' => $this->improvementSuggestions,
            'confidential_responses' => $this->confidentialResponses,
            'risk_flags' => $this->riskFlags,
            'scoring_inputs' => $this->scoringInputs,
        ];
    }
}
