<?php

namespace App\Application\Approvals\Actions;

use App\Application\Approvals\Data\ApprovalCenterContextData;
use App\Application\Approvals\Data\ApprovalCenterPageData;
use App\Models\User;
use App\Services\Builder360\ApprovalCenterService;

final class ListApprovalCenter
{
    public function __construct(private readonly ApprovalCenterService $approvals) {}

    public function execute(User $actor, ApprovalCenterContextData $context): ApprovalCenterPageData
    {
        $payload = $this->approvals->payloadFor(
            $actor,
            $context->roleSlug,
            $context->projectId,
            $context->filters,
        ) ?? $this->restrictedPayload();

        return new ApprovalCenterPageData($payload, $context->filters);
    }

    /** @return array<string, mixed> */
    private function restrictedPayload(): array
    {
        return [
            'restricted' => true,
            'message' => 'Approval Center is not available for this role.',
            'summary' => [
                'pending' => 0, 'high_priority' => 0, 'actionable' => 0, 'restricted' => 0,
                'approved' => 0, 'value_tagged' => 0, 'total_value' => 0, 'modules' => [],
            ],
            'filters' => ['modules' => [], 'priorities' => [], 'statuses' => []],
            'rows' => [],
            'pagination' => ['page' => 1, 'per_page' => 25, 'total' => 0, 'last_page' => 1],
        ];
    }
}
