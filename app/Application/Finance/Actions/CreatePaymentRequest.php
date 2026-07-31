<?php
namespace App\Application\Finance\Actions;
use App\Application\Finance\Data\FinanceCommandData;
use App\Models\PaymentRequest;
use App\Services\Finance\PaymentRequestService;
final class CreatePaymentRequest
{
    public function __construct(private readonly PaymentRequestService $payments) {}
    public function execute(FinanceCommandData $command): PaymentRequest { return $this->payments->create($command->attributes,$command->actor,$command->request); }
}
