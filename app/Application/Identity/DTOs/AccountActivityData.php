<?php

namespace App\Application\Identity\DTOs;

final readonly class AccountActivityData
{
    public function __construct(
        public string $action,
        public string $event,
        public string $occurredAt,
    ) {
    }
}
