<?php

namespace App\Application\Buyer\Actions;

use App\Application\Buyer\Data\BuyerPortalWorkspaceData;
use App\Domain\Buyer\Services\BuyerPortalRegister;
use App\Models\User;

final class ListBuyerPaymentRequests
{
    public function __construct(private readonly BuyerPortalRegister $register) {}
    public function execute(User $actor, array $filters): BuyerPortalWorkspaceData
    {
        return new BuyerPortalWorkspaceData('payment-requests', $this->register->customer($actor), $this->register->paymentRequests($actor, $filters), $filters,
            ['requested' => 'Requested', 'paid' => 'Paid', 'expired' => 'Expired', 'cancelled' => 'Cancelled', 'failed' => 'Failed']);
    }
}
