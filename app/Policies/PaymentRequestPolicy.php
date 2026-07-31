<?php

namespace App\Policies;

use App\Models\PaymentRequest;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class PaymentRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('collections.view')
            || $user->hasPermission('collections.manage')
            || $user->hasPermission('collections.approve')
            || $user->hasPermission('finance.view')
            || $user->hasPermission('finance.manage')
            || $user->hasPermission('finance.approve')
            || $user->hasPermission('buyer.view');
    }

    public function view(User $user, PaymentRequest $paymentRequest): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($user->hasPermission('buyer.view')) {
            return $paymentRequest->customer()->where('portal_user_id', $user->id)->exists();
        }

        return app(CompanyScopeService::class)->allows($user, $paymentRequest->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('collections.manage');
    }

    public function pay(User $user, PaymentRequest $paymentRequest): bool
    {
        return $paymentRequest->status === 'requested'
            && $paymentRequest->customer()->where('portal_user_id', $user->id)->exists();
    }

    public function cancel(User $user, PaymentRequest $paymentRequest): bool
    {
        return $user->hasPermission('collections.manage')
            && $paymentRequest->status === 'requested'
            && app(CompanyScopeService::class)->allows($user, $paymentRequest->company_id);
    }
}
