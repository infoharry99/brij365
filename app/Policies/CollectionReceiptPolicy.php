<?php

namespace App\Policies;

use App\Models\CollectionReceipt;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class CollectionReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('collections.view')
            || $user->hasPermission('collections.manage')
            || $user->hasPermission('collections.approve');
    }

    public function view(User $user, CollectionReceipt $collectionReceipt): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return app(CompanyScopeService::class)->allows($user, $collectionReceipt->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('collections.manage');
    }

    public function approve(User $user, CollectionReceipt $collectionReceipt): bool
    {
        if (! $user->hasPermission('collections.approve')) {
            return false;
        }

        return $collectionReceipt->status === 'submitted'
            && app(CompanyScopeService::class)->allows($user, $collectionReceipt->company_id)
            && $collectionReceipt->collected_by_user_id !== $user->id;
    }
}
