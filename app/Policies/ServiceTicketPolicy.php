<?php

namespace App\Policies;

use App\Models\ServiceTicket;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class ServiceTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('after_sales.view')
            || $user->hasPermission('after_sales.manage')
            || $user->hasPermission('after_sales.approve')
            || $user->hasPermission('buyer.view');
    }

    public function view(User $user, ServiceTicket $serviceTicket): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($this->isBuyerPortalUser($user)) {
            return $serviceTicket->customer()->where('portal_user_id', $user->id)->exists();
        }

        return app(CompanyScopeService::class)->allows($user, $serviceTicket->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('after_sales.manage') || $user->hasPermission('buyer.view');
    }

    public function assign(User $user, ServiceTicket $serviceTicket): bool
    {
        return $user->hasPermission('after_sales.manage')
            && $serviceTicket->status !== 'closed'
            && app(CompanyScopeService::class)->allows($user, $serviceTicket->company_id);
    }

    public function resolve(User $user, ServiceTicket $serviceTicket): bool
    {
        return $user->hasPermission('after_sales.manage')
            && in_array($serviceTicket->status, ['open', 'assigned', 'in_progress'], true)
            && app(CompanyScopeService::class)->allows($user, $serviceTicket->company_id);
    }

    public function close(User $user, ServiceTicket $serviceTicket): bool
    {
        if ($serviceTicket->status !== 'resolved') {
            return false;
        }

        if ($user->hasPermission('after_sales.approve') && app(CompanyScopeService::class)->allows($user, $serviceTicket->company_id)) {
            return true;
        }

        return $this->isBuyerPortalUser($user)
            && $serviceTicket->customer()->where('portal_user_id', $user->id)->exists();
    }

    private function isBuyerPortalUser(User $user): bool
    {
        return $user->role?->slug === 'buyer' && $user->hasPermission('buyer.view');
    }
}
