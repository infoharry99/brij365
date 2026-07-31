<?php

namespace App\Policies;

use App\Models\DataImportBatch;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class DataImportBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('settings.view') || $user->hasPermission('settings.manage');
    }

    public function view(User $user, DataImportBatch $dataImportBatch): bool
    {
        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $dataImportBatch->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('settings.manage');
    }

    public function post(User $user, DataImportBatch $dataImportBatch): bool
    {
        return $user->hasPermission('settings.manage')
            && app(CompanyScopeService::class)->allows($user, $dataImportBatch->company_id);
    }
}
