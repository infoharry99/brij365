<?php

namespace App\Application\Projects\Data;

final readonly class ProjectHealthEvidenceData
{
    public function __construct(
        public float $constructionProgress,
        public float $salesProgress,
        public float $collectionProgress,
        public float $budgetControl,
        public float $scheduleVariance,
        public float $inventoryHealth,
        public float $approvalDelays,
        public float $procurementDelays,
        public float $receivables,
    ) {}

    /** @param array<string, mixed> $data */
    public static function from(array $data): self
    {
        return new self(
            (float) $data['construction_progress'],
            (float) $data['sales_progress'],
            (float) $data['collection_progress'],
            (float) $data['budget_control'],
            (float) $data['schedule_variance'],
            (float) $data['inventory_health'],
            (float) $data['approval_delays'],
            (float) $data['procurement_delays'],
            (float) $data['receivables'],
        );
    }

    /** @return array<string, float> */
    public function toArray(): array
    {
        return [
            'construction_progress' => $this->constructionProgress,
            'sales_progress' => $this->salesProgress,
            'collection_progress' => $this->collectionProgress,
            'budget_control' => $this->budgetControl,
            'schedule_variance' => $this->scheduleVariance,
            'inventory_health' => $this->inventoryHealth,
            'approval_delays' => $this->approvalDelays,
            'procurement_delays' => $this->procurementDelays,
            'receivables' => $this->receivables,
        ];
    }
}
