<?php

namespace App\Application\Hr\Data;

final readonly class HrSettingRowData
{
    public function __construct(
        public int $id,
        public string $settingKey,
        public string $settingGroup,
        public string $label,
        public string $description,
        public string $scopeLabel,
        public string $versionLabel,
        public string $typeLabel,
        public string $valueSummary,
        public string $status,
        public string $statusLabel,
        public string $statusTone,
        public string $effectiveLabel,
        public string $makerLabel,
        public string $checkerLabel,
        public bool $canApprove,
    ) {}
}
