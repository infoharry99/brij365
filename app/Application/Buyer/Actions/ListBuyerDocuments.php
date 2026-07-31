<?php

namespace App\Application\Buyer\Actions;

use App\Application\Buyer\Data\BuyerPortalWorkspaceData;
use App\Domain\Buyer\Services\BuyerPortalRegister;
use App\Models\User;

final class ListBuyerDocuments
{
    public function __construct(private readonly BuyerPortalRegister $register) {}
    public function execute(User $actor, array $filters): BuyerPortalWorkspaceData
    {
        return new BuyerPortalWorkspaceData('documents', $this->register->customer($actor), $this->register->documents($actor, $filters), $filters);
    }
}
