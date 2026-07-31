<?php
namespace App\Application\Finance\Actions;
use App\Application\Finance\Data\GstReturnPeriodWorkspaceData;
use App\Domain\Finance\Services\FinanceWorkspaceRegister;
use App\Models\GstReturnPeriod;
use App\Models\User;
final class ListGstReturnPeriodWorkspace
{
    public function __construct(private readonly FinanceWorkspaceRegister $register) {}
    public function execute(User $actor,array $filters): GstReturnPeriodWorkspaceData
    {
        return new GstReturnPeriodWorkspaceData(periods:$this->register->gstReturnPeriods($actor,$filters),filters:$filters,
            statuses:['prepared'=>'Prepared','approved'=>'Approved','locked'=>'Locked'],abilities:['canPreparePeriod'=>$actor->can('create',GstReturnPeriod::class)]);
    }
}
