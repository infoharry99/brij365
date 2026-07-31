<?php

namespace App\Application\Scoring\DTOs;

final readonly class RosterImpactSimulationResultData
{
    /**
     * @param list<array<string, mixed>> $days
     * @param list<array{code:string,date:string,message:string,tone:string}> $findings
     * @param array<string, int> $counts
     * @param array<string, mixed> $ruleContext
     */
    public function __construct(
        public int $rotationRuleId,
        public string $rotationName,
        public string $employeeName,
        public string $employeeCode,
        public string $startDate,
        public string $endDate,
        public string $timezone,
        public array $days,
        public array $findings,
        public array $counts,
        public array $ruleContext,
        public string $inputHash,
        public string $resultHash,
    ) {
    }

    /** @return array<string, mixed> */
    public function toView(): array
    {
        return [
            'rotation_rule_id' => $this->rotationRuleId,
            'rotation_name' => $this->rotationName,
            'employee_name' => $this->employeeName,
            'employee_code' => $this->employeeCode,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'timezone' => $this->timezone,
            'days' => $this->days,
            'findings' => $this->findings,
            'counts' => $this->counts,
            'rule_context' => $this->ruleContext,
            'input_hash' => $this->inputHash,
            'result_hash' => $this->resultHash,
            'mutated_records' => 0,
        ];
    }
}
