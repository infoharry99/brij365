<?php

namespace App\Application\Hr\Data;

final readonly class ConfirmationRecommendationData
{
    /** @param array<string, float> $reviewScores */
    public function __construct(
        public string $recommendation,
        public string $comments,
        public array $reviewScores,
    ) {}

    /** @param array<string, mixed> $data */
    public static function from(array $data): self
    {
        return new self(
            recommendation: (string) $data['manager_recommendation'],
            comments: (string) $data['manager_comments'],
            reviewScores: collect($data['review_scores'] ?? [])->map(
                static fn (mixed $score): float => (float) $score,
            )->all(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'manager_recommendation' => $this->recommendation,
            'manager_comments' => $this->comments,
            'review_scores' => $this->reviewScores,
        ];
    }
}
