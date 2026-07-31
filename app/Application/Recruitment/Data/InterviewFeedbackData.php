<?php

namespace App\Application\Recruitment\Data;

final readonly class InterviewFeedbackData
{
    /** @param array<string, float> $competencyScores */
    public function __construct(
        public int $rating,
        public string $recommendation,
        public ?string $strengths,
        public ?string $concerns,
        public ?string $feedbackNote,
        public ?string $nextAction,
        public float $panelWeight,
        public array $competencyScores,
    ) {}

    /** @param array<string, mixed> $data */
    public static function from(array $data): self
    {
        return new self(
            rating: (int) $data['rating'],
            recommendation: (string) $data['recommendation'],
            strengths: $data['strengths'] ?? null,
            concerns: $data['concerns'] ?? null,
            feedbackNote: $data['feedback_note'] ?? null,
            nextAction: $data['next_action'] ?? null,
            panelWeight: (float) ($data['panel_weight'] ?? 1),
            competencyScores: collect($data['competency_scores'] ?? [])->map(
                static fn (mixed $score): float => (float) $score,
            )->all(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'rating' => $this->rating,
            'recommendation' => $this->recommendation,
            'strengths' => $this->strengths,
            'concerns' => $this->concerns,
            'feedback_note' => $this->feedbackNote,
            'next_action' => $this->nextAction,
            'panel_weight' => $this->panelWeight,
            'competency_scores' => $this->competencyScores,
        ];
    }
}
