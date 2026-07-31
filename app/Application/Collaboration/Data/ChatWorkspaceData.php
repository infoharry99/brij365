<?php

namespace App\Application\Collaboration\Data;

use App\Models\ChatConversation;
use Illuminate\Support\Collection;

final readonly class ChatWorkspaceData
{
    /** @param array<string,mixed> $filters @param array<string,string> $conversationTypes */
    public function __construct(
        public Collection $conversations,
        public ?ChatConversation $selectedConversation,
        public Collection $messages,
        public array $filters,
        public Collection $projects,
        public Collection $users,
        public array $options,
        public array $conversationTypes,
        public bool $canCreate,
    ) {}
}
