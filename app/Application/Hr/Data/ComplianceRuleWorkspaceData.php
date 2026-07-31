<?php

namespace App\Application\Hr\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class ComplianceRuleWorkspaceData
{
    public function __construct(
        public LengthAwarePaginator $settings,
        public Collection $companies,
        public array $settingKeys,
        public ComplianceRuleSummaryData $summary,
        public array $abilities,
    ) {}

    public function toView(): array { return get_object_vars($this); }
}
