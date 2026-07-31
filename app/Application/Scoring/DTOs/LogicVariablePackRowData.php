<?php

namespace App\Application\Scoring\DTOs;

final readonly class LogicVariablePackRowData
{
    /**
     * @param  list<array{key:string,label:string,value:string}>  $variables
     */
    public function __construct(
        public int $id,
        public string $settingKey,
        public string $label,
        public string $domain,
        public string $status,
        public int $version,
        public string $effectivePeriod,
        public string $sourceAuthority,
        public string $sourceReference,
        public string $checksum,
        public bool $verified,
        public bool $requiresVerification,
        public array $variables,
        public bool $canApprove,
        public ?string $reviewUrl,
    ) {
    }
}
