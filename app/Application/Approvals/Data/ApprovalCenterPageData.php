<?php

namespace App\Application\Approvals\Data;

final readonly class ApprovalCenterPageData
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $filters
     */
    public function __construct(public array $payload, public array $filters) {}
}
