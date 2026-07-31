<?php

namespace App\Application\AfterSales\Data;

final readonly class CloseServiceTicketData
{
    /** @param array<string, float> $scoringInputs */
    public function __construct(
        public ?int $customerRating,
        public ?string $note,
        public array $scoringInputs,
    ) {}

    /** @param array<string, mixed> $data */
    public static function from(array $data): self
    {
        return new self(
            customerRating: isset($data['customer_rating']) ? (int) $data['customer_rating'] : null,
            note: $data['note'] ?? null,
            scoringInputs: collect($data['scoring_inputs'] ?? [])->map(
                static fn (mixed $score): float => (float) $score,
            )->all(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'customer_rating' => $this->customerRating,
            'note' => $this->note,
            'scoring_inputs' => $this->scoringInputs,
        ];
    }
}
