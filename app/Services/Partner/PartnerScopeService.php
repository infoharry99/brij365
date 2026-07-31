<?php

namespace App\Services\Partner;

use App\Models\Partner;
use App\Models\User;

class PartnerScopeService
{
    /**
     * @return array<int, int>
     */
    public function activePartnerIdsForUser(?User $user): array
    {
        if (
            ! $user
            || ! $user->email
            || $user->role?->scope_level !== 'partner'
            || $user->can('partner.portal') !== true
        ) {
            return [];
        }

        return Partner::query()
            ->where('email', $user->email)
            ->where('status', 'active')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }
}
