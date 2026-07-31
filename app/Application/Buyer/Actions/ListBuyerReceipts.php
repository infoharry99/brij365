<?php

namespace App\Application\Buyer\Actions;

use App\Application\Buyer\Data\BuyerPortalWorkspaceData;
use App\Domain\Buyer\Services\BuyerPortalRegister;
use App\Models\User;

final class ListBuyerReceipts
{
    public function __construct(private readonly BuyerPortalRegister $register) {}
    public function execute(User $actor, array $filters): BuyerPortalWorkspaceData
    {
        return new BuyerPortalWorkspaceData('receipts', $this->register->customer($actor), $this->register->receipts($actor, $filters), $filters);
    }
}
