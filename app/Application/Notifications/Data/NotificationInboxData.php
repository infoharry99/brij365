<?php

namespace App\Application\Notifications\Data;

use Illuminate\Pagination\LengthAwarePaginator;

final readonly class NotificationInboxData
{
    /**
     * @param LengthAwarePaginator<int, \App\Models\UserNotification> $notifications
     * @param array<string, mixed> $summary
     * @param array<string, mixed> $filteredSummary
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $filterOptions
     * @param array<string, string> $statuses
     * @param array<string, string> $severities
     * @param array<int, string> $categories
     */
    public function __construct(
        public LengthAwarePaginator $notifications,
        public array $summary,
        public array $filteredSummary,
        public array $filters,
        public array $filterOptions,
        public array $statuses,
        public array $severities,
        public array $categories,
    ) {}
}
