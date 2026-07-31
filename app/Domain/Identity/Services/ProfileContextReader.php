<?php

namespace App\Domain\Identity\Services;

use App\Models\User;
use App\Services\Builder360\Builder360Bootstrap;

final class ProfileContextReader
{
    public function __construct(private readonly Builder360Bootstrap $contexts) {}

    /** @return array<string, mixed> */
    public function read(User $actor, ?string $roleSlug, ?int $projectId): array
    {
        return $this->contexts->identityContextForRoleContext($actor, $roleSlug, $projectId);
    }
}
