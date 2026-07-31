<?php
namespace App\Application\Finance\Actions;
use App\Application\Finance\Data\FinanceCommandData;
use App\Models\FinancialVoucher;
use App\Services\Finance\FinancialVoucherService;
final class RejectFinancialVoucher
{
    public function __construct(private readonly FinancialVoucherService $vouchers) {}
    public function execute(FinancialVoucher $voucher, FinanceCommandData $command): FinancialVoucher { return $this->vouchers->reject($voucher,$command->attributes,$command->actor,$command->request); }
}
