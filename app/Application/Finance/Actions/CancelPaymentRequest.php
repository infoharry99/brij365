<?php
namespace App\Application\Finance\Actions;
use App\Application\Finance\Data\FinanceCommandData;
use App\Models\PaymentRequest;
use App\Services\Finance\PaymentRequestService;
final class CancelPaymentRequest
{
    public function __construct(private readonly PaymentRequestService $payments) {}
    public function execute(PaymentRequest $payment, FinanceCommandData $command): PaymentRequest { return $this->payments->cancel($payment,$command->attributes,$command->actor,$command->request); }
}
