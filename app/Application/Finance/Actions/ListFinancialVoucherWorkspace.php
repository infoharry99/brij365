<?php
namespace App\Application\Finance\Actions;
use App\Application\Finance\Data\FinancialVoucherWorkspaceData;
use App\Domain\Finance\Services\FinanceWorkspaceRegister;
use App\Models\FinancialVoucher;
use App\Models\User;
final class ListFinancialVoucherWorkspace
{
    public function __construct(private readonly FinanceWorkspaceRegister $register) {}
    public function execute(User $actor, array $filters): FinancialVoucherWorkspaceData
    {
        return new FinancialVoucherWorkspaceData(
            vouchers: $this->register->vouchers($actor, $filters), filters: $filters,
            companies: $this->register->companies($actor), projects: $this->register->projects($actor),
            statuses: ['submitted'=>'Submitted','approved'=>'Approved','rejected'=>'Rejected','void'=>'Void'],
            voucherTypes: ['receipt'=>'Receipt','payment'=>'Payment','journal'=>'Journal','contra'=>'Contra','debit_note'=>'Debit Note','credit_note'=>'Credit Note'],
            lineTypes: ['debit'=>'Debit','credit'=>'Credit'], abilities: ['canCreateVoucher'=>$actor->can('create', FinancialVoucher::class)],
        );
    }
}
