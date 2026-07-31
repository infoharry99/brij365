<?php
namespace App\Application\Finance\Actions;
use App\Application\Finance\Data\FinanceCommandData;
use App\Models\FinancialVoucher;
use App\Services\Finance\FinancialVoucherService;
final class SubmitFinancialVoucher
{
    public function __construct(private readonly FinancialVoucherService $vouchers) {}
    public function execute(FinanceCommandData $command): FinancialVoucher { return $this->vouchers->submit($command->attributes,$command->actor,$command->request); }
}
