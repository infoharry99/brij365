<?php

namespace App\Application\Search\Actions;

use App\Application\Search\DTOs\GlobalSearchPageData;
use App\Domain\Search\Services\FindAuthorizedBusinessRecords;
use App\Models\User;

final class SearchBuilder360
{
    public function __construct(private readonly FindAuthorizedBusinessRecords $finder)
    {
    }

    public function handle(User $user, string $query): GlobalSearchPageData
    {
        $query = trim($query);
        $groups = $this->finder->forUser($user, $query);

        return new GlobalSearchPageData(
            query: $query,
            groups: $groups,
            total: collect($groups)->sum(static fn ($group): int => count($group->results)),
        );
    }
}
