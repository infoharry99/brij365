<?php

namespace App\Application\Buyer\Actions;

use App\Application\Finance\Data\FinanceCommandData;
use App\Models\PaymentRequest;
use App\Services\Finance\PaymentRequestService;

final class PayBuyerPaymentRequest
{
    public function __construct(private readonly PaymentRequestService $payments) {}
    public function execute(PaymentRequest $paymentRequest, FinanceCommandData $command): PaymentRequest
    {
        return $this->payments->markPaid($paymentRequest, $command->attributes, $command->actor, $command->request);
    }
}
