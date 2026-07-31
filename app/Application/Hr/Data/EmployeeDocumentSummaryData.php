<?php

namespace App\Application\Hr\Data;

final readonly class EmployeeDocumentSummaryData
{
    public function __construct(
        public int $total,
        public int $submitted,
        public int $approved,
        public int $expiringSoon,
        public int $expired,
    ) {}
}
