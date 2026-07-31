<?php

namespace App\Application\Finance\Actions;

use App\Application\Finance\Data\CollectionReceiptWorkspaceData;
use App\Domain\Finance\Services\FinanceWorkspaceRegister;
use App\Models\CollectionReceipt;
use App\Models\User;

final class ListCollectionReceiptWorkspace
{
    public function __construct(private readonly FinanceWorkspaceRegister $register) {}

    public function execute(User $actor, array $filters): CollectionReceiptWorkspaceData
    {
        return new CollectionReceiptWorkspaceData(
            receipts: $this->register->receipts($actor, $filters),
            filters: $filters,
            bookings: $this->register->bookings($actor),
            projects: $this->register->projects($actor, ['id', 'company_id', 'code', 'name']),
            customers: $this->register->customers($actor),
            statuses: ['submitted' => 'Submitted', 'approved' => 'Approved', 'rejected' => 'Rejected'],
            paymentModes: ['cash' => 'Cash', 'cheque' => 'Cheque', 'neft' => 'NEFT', 'rtgs' => 'RTGS', 'upi' => 'UPI', 'online' => 'Online'],
            abilities: ['canCreateReceipt' => $actor->can('create', CollectionReceipt::class)],
        );
    }
}
