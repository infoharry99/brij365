<?php

namespace App\Application\Approvals\Data;

final readonly class ApprovalCenterContextData
{
    /** @param array<string, mixed> $filters */
    public function __construct(
        public string $roleSlug,
        public ?int $projectId,
        public array $filters,
    ) {}
}
