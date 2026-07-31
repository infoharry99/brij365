<?php
namespace App\Application\Finance\Actions;
use App\Application\Finance\Data\FinanceCommandData;
use App\Models\FinancialVoucher;
use App\Services\Finance\FinancialVoucherService;
final class ApproveFinancialVoucher
{
    public function __construct(private readonly FinancialVoucherService $vouchers) {}
    public function execute(FinancialVoucher $voucher, FinanceCommandData $command): FinancialVoucher { return $this->vouchers->approve($voucher,$command->attributes,$command->actor,$command->request); }
}
