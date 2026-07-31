<?php

namespace App\Application\Finance\Actions;

use App\Application\Finance\Data\FinanceCommandData;
use App\Models\CollectionReceipt;
use App\Services\Finance\CollectionReceiptService;

final class SubmitCollectionReceipt
{
    public function __construct(private readonly CollectionReceiptService $collections) {}
    public function execute(FinanceCommandData $command): CollectionReceipt
    {
        return $this->collections->submit($command->attributes, $command->actor, $command->request);
    }
}
