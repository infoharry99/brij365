<?php

namespace App\Application\Scoring\DTOs;

final readonly class RosterSimulationRuleData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $employeeName,
        public string $employeeCode,
        public string $anchorDate,
        public int $cycleDays,
        public int $generationHorizonDays,
        public string $status,
        public int $lockVersion,
    ) {
    }
}
