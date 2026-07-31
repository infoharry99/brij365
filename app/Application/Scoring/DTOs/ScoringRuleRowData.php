<?php

namespace App\Application\Scoring\DTOs;

final readonly class ScoringRuleRowData
{
    public function __construct(
        public int $id,
        public string $ruleKey,
        public string $name,
        public int $version,
        public string $status,
        public string $effectiveAt,
        public string $createdBy,
        public string $checksum,
        public bool $canUpdate,
        public bool $canClone,
        public bool $canReject,
        public bool $canRetire,
        public bool $canRecalculate,
        public bool $canValidate,
        public bool $canSubmit,
        public bool $canApprove,
        public bool $canActivate,
    ) {
    }
}
