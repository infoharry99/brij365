<?php

namespace App\Application\Collaboration\Data;

use Illuminate\Support\Collection;

final readonly class ChatConversationIndexData
{
    /** @param array<int,array<int,array<string,mixed>>> $messages */
    public function __construct(
        public Collection $conversations,
        public array $messages,
    ) {}
}
