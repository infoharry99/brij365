<?php

namespace App\Application\Hr\Data;

final readonly class ComplianceRuleRowData
{
    public function __construct(
        public int $id,
        public string $label,
        public string $settingKey,
        public string $settingType,
        public int $version,
        public string $scope,
        public string $effectiveFrom,
        public string $createdBy,
        public string $approvalState,
        public string $status,
        public string $statusLabel,
        public string $statusTone,
        public bool $governedStatutoryPack,
        public bool $statutoryValidationRequired,
        public bool $verified,
        public string $verificationLabel,
        public string $sourceAuthority,
        public string $sourceReference,
        public ?string $verifiedBy,
        public bool $canVerify,
        public bool $canApprove,
    ) {}
}
