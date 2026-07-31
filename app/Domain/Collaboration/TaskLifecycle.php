<?php

namespace App\Domain\Collaboration;

final class TaskLifecycle
{
    /** @return array<string, string> */
    public static function statuses(): array
    {
        return [
            'draft' => 'Draft',
            'open' => 'Open',
            'assigned' => 'Assigned',
            'accepted' => 'Accepted',
            'in_progress' => 'In Progress',
            'on_hold' => 'On Hold',
            'waiting_info' => 'Waiting Info',
            'waiting_dependency' => 'Waiting Dependency',
            'under_review' => 'Under Review',
            'waiting_approval' => 'Waiting Approval',
            'blocked' => 'Blocked',
            'completed' => 'Completed',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
        ];
    }

    /** @return array<int, string> */
    public static function terminalStatuses(): array
    {
        return ['completed', 'rejected', 'cancelled'];
    }

    /** @return array<int, string> */
    public static function openStatuses(): array
    {
        return array_values(array_diff(array_keys(self::statuses()), self::terminalStatuses()));
    }

    public static function boardColumn(string $status, bool $hasAssignee, bool $hasPendingApproval): string
    {
        if ($hasPendingApproval || $status === 'waiting_approval') {
            return 'approval';
        }

        return match ($status) {
            'draft' => 'backlog',
            'open' => $hasAssignee ? 'todo' : 'backlog',
            'assigned', 'accepted' => 'todo',
            'in_progress' => 'in_progress',
            'under_review' => 'review',
            'on_hold', 'waiting_info', 'waiting_dependency', 'blocked', 'rejected' => 'blocked',
            'completed' => 'completed',
            default => 'cancelled',
        };
    }
}
