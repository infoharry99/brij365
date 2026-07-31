<?php

namespace App\Application\Collaboration\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use App\Models\CollaborationMessage;

final readonly class MailboxWorkspaceData
{
    /** @param array<string,mixed> $filters */
    public function __construct(
        public LengthAwarePaginator $messages,
        public ?CollaborationMessage $selectedMessage,
        public array $filters,
        public Collection $companies,
        public Collection $projects,
        public Collection $users,
        public array $folders,
        public array $statuses,
        public array $priorities,
        public bool $canCreate,
    ) {}
}
