<?php
namespace App\Application\Crm\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class ProspectInquiryWorkspaceData
{
    /** @param array<string,mixed> $filters */
    public function __construct(
        public LengthAwarePaginator $inquiries,
        public array $filters,
        public Collection $projects,
        public Collection $campaigns,
        public Collection $assignees,
        public Collection $sources,
        public Collection $channels,
        public array $statuses,
        public Collection $metrics,
        public bool $canManage,
    ) {}
}
