<?php

namespace App\Application\Scoring\DTOs;

final readonly class PerformanceSimulationRuleData
{
    /** @param list<array{key:string,label:string,weight:float,required:bool,missing_data_behavior:string}> $criteria */
    public function __construct(
        public int $id,
        public string $name,
        public int $version,
        public string $status,
        public string $checksum,
        public array $criteria,
    ) {
    }
}
