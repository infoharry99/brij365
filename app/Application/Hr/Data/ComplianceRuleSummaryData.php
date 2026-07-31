<?php

namespace App\Application\Hr\Data;

final readonly class ComplianceRuleSummaryData
{
    public function __construct(
        public int $total,
        public int $draft,
        public int $active,
        public int $archived,
        public int $verificationRequired,
    ) {}
}
