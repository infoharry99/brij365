<?php

namespace App\Application\Finance\Actions;

use App\Application\Finance\Data\FinanceCommandData;
use App\Models\CollectionReceipt;
use App\Services\Finance\CollectionReceiptService;

final class ApproveCollectionReceipt
{
    public function __construct(private readonly CollectionReceiptService $collections) {}
    public function execute(CollectionReceipt $receipt, FinanceCommandData $command): CollectionReceipt
    {
        return $this->collections->approve($receipt, $command->actor, $command->attributes, $command->request);
    }
}
