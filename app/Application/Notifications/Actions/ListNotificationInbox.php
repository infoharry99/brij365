<?php

namespace App\Application\Notifications\Actions;

use App\Application\Notifications\Data\NotificationInboxData;
use App\Domain\Notifications\Services\NotificationFilterCatalog;
use App\Models\User;
use App\Services\Notifications\NotificationQueryService;
use App\Services\Notifications\NotificationSummaryService;
use App\Support\PaginationPolicy;

final class ListNotificationInbox
{
    public function __construct(
        private readonly NotificationQueryService $queries,
        private readonly NotificationSummaryService $summaries,
        private readonly NotificationFilterCatalog $filterCatalog,
        private readonly PaginationPolicy $pagination,
    ) {}

    /** @param array<string, mixed> $filters */
    public function execute(User $user, array $filters): NotificationInboxData
    {
        $perPage = (int) ($filters['per_page'] ?? $this->pagination->workspacePerPage());
        $options = $this->queries->filterOptionsFor($user);

        return new NotificationInboxData(
            notifications: $this->queries->paginateFor($user, $filters, $perPage),
            summary: $this->summaries->summaryFor($user),
            filteredSummary: $this->summaries->filteredCountsFor($user, $filters),
            filters: $filters,
            filterOptions: $options,
            statuses: $this->filterCatalog->statuses(),
            severities: $this->filterCatalog->severities(),
            categories: array_values($options['categories'] ?? []),
        );
    }
}
