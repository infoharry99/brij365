<?php

namespace App\Application\Projects\Data;

final readonly class ProjectCostRoiExportData
{
    public function __construct(
        public string $content,
        public int $rowCount,
        public string $filename,
    ) {}
}
