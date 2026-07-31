<?php

namespace App\Application\Procurement\Data;

final readonly class VendorPerformanceEvidenceData
{
    public function __construct(
        public float $acceptanceRate,
        public float $quality,
        public float $onTimeDelivery,
        public float $fulfillment,
        public float $priceCompetitiveness,
        public float $documentation,
        public float $responsiveness,
        public float $issueResolution,
    ) {}

    /** @param array<string, mixed> $data */
    public static function from(array $data): self
    {
        return new self(
            (float) $data['acceptance_rate'],
            (float) $data['quality'],
            (float) $data['on_time_delivery'],
            (float) $data['fulfillment'],
            (float) $data['price_competitiveness'],
            (float) $data['documentation'],
            (float) $data['responsiveness'],
            (float) $data['issue_resolution'],
        );
    }

    /** @return array<string, float> */
    public function toArray(): array
    {
        return [
            'acceptance_rate' => $this->acceptanceRate,
            'quality' => $this->quality,
            'on_time_delivery' => $this->onTimeDelivery,
            'fulfillment' => $this->fulfillment,
            'price_competitiveness' => $this->priceCompetitiveness,
            'documentation' => $this->documentation,
            'responsiveness' => $this->responsiveness,
            'issue_resolution' => $this->issueResolution,
        ];
    }
}
