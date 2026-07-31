<?php

namespace App\Application\Hr\Data;

final readonly class HrReportCatalogData
{
    /**
     * @param  array<int, array<string, mixed>>  $reports
     */
    public function __construct(
        public array $reports,
        public bool $compensationVisible,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toView(): array
    {
        return [
            'reports' => $this->reports,
            'compensationVisible' => $this->compensationVisible,
            'reportCount' => count($this->reports),
        ];
    }
}
