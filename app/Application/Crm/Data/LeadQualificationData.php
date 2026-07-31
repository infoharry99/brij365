<?php

namespace App\Application\Crm\Data;

final readonly class LeadQualificationData
{
    /**
     * @param  array<string, string|null>  $qualityConditions
     * @param  array<string, int>  $componentScores
     * @param  array<int|string, mixed>  $requirements
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public int $leadId,
        public string $status,
        public array $qualityConditions,
        public array $componentScores,
        public ?string $preferredConfiguration,
        public ?float $verifiedBudgetMin,
        public ?float $verifiedBudgetMax,
        public ?string $expectedBookingDate,
        public string $decisionNotes,
        public array $requirements,
        public array $metadata,
    ) {}

    /** @param array<string, mixed> $data */
    public static function from(array $data): self
    {
        $componentScores = collect($data)
            ->filter(static fn (mixed $value, string $key): bool => str_ends_with($key, '_score') && $value !== null)
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();

        return new self(
            leadId: (int) $data['lead_id'],
            status: (string) $data['status'],
            qualityConditions: $data['quality_conditions'] ?? [],
            componentScores: $componentScores,
            preferredConfiguration: $data['preferred_configuration'] ?? null,
            verifiedBudgetMin: isset($data['verified_budget_min']) ? (float) $data['verified_budget_min'] : null,
            verifiedBudgetMax: isset($data['verified_budget_max']) ? (float) $data['verified_budget_max'] : null,
            expectedBookingDate: $data['expected_booking_date'] ?? null,
            decisionNotes: (string) $data['decision_notes'],
            requirements: $data['requirements'] ?? [],
            metadata: $data['metadata'] ?? [],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_merge([
            'lead_id' => $this->leadId,
            'status' => $this->status,
            'quality_conditions' => $this->qualityConditions,
            'preferred_configuration' => $this->preferredConfiguration,
            'verified_budget_min' => $this->verifiedBudgetMin,
            'verified_budget_max' => $this->verifiedBudgetMax,
            'expected_booking_date' => $this->expectedBookingDate,
            'decision_notes' => $this->decisionNotes,
            'requirements' => $this->requirements,
            'metadata' => $this->metadata,
        ], $this->componentScores);
    }
}
