<?php

namespace App\Application\Hr\Data;

use Illuminate\Pagination\LengthAwarePaginator;

final readonly class HrSettingsWorkspaceData
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, int>  $summary
     * @param  array<string, string>  $tabs
     */
    public function __construct(
        public LengthAwarePaginator $settings,
        public array $filters,
        public array $summary,
        public array $tabs,
        public bool $canManage,
        public bool $canApprove,
        public bool $canViewRoles,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toView(): array
    {
        return get_object_vars($this);
    }
}
