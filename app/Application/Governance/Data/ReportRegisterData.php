<?php

namespace App\Application\Governance\Data;

final readonly class ReportRegisterData
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $filters
     * @param array<string, string> $reports
     * @param array<int, array{id:int, code:string, name:string}> $projects
     */
    public function __construct(
        public string $report,
        public string $format,
        public array $rows,
        public array $filters,
        public array $reports,
        public array $projects,
    ) {}
}
