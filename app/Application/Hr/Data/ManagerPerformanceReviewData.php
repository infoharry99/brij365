<?php

namespace App\Application\Hr\Data;

final readonly class ManagerPerformanceReviewData
{
    /** @var list<string> */
    public const ALLOWED_SCORING_INPUTS = [
        'kpi_achievement',
        'kra_achievement',
        'competencies',
        'behaviour',
    ];

    /** @param array<int, array<string, mixed>>|null $kpis @param array<string, float> $scoringInputs */
    public function __construct(
        public float $managerScore,
        public string $comments,
        public ?array $kpis,
        public array $scoringInputs,
    ) {}

    /** @param array<string, mixed> $data */
    public static function from(array $data): self
    {
        return new self(
            managerScore: (float) $data['manager_score'],
            comments: (string) $data['manager_comments'],
            kpis: $data['kpis'] ?? null,
            scoringInputs: collect($data['scoring_inputs'] ?? [])->only(self::ALLOWED_SCORING_INPUTS)->map(
                static fn (mixed $score): float => (float) $score,
            )->all(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = [
            'manager_score' => $this->managerScore,
            'manager_comments' => $this->comments,
            'scoring_inputs' => $this->scoringInputs,
        ];

        if ($this->kpis !== null) {
            $payload['kpis'] = $this->kpis;
        }

        return $payload;
    }
}
