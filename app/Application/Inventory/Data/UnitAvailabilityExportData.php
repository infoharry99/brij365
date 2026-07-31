<?php

namespace App\Application\Inventory\Data;

final readonly class UnitAvailabilityExportData
{
    public function __construct(public string $content, public int $rowCount, public string $filename) {}
}
