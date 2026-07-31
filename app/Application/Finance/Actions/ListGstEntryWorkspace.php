<?php
namespace App\Application\Finance\Actions;
use App\Application\Finance\Data\GstEntryWorkspaceData;
use App\Domain\Finance\Services\FinanceWorkspaceRegister;
use App\Models\GstEntry;
use App\Models\User;
final class ListGstEntryWorkspace
{
    public function __construct(private readonly FinanceWorkspaceRegister $register) {}
    public function execute(User $actor,array $filters): GstEntryWorkspaceData
    {
        return new GstEntryWorkspaceData(entries:$this->register->gstEntries($actor,$filters),filters:$filters,projects:$this->register->projects($actor),
            statuses:['submitted'=>'Submitted','approved'=>'Approved','locked'=>'Locked'],
            transactionTypes:['output'=>'Output tax','input'=>'Input credit','reverse_charge'=>'Reverse charge','adjustment'=>'Adjustment'],
            abilities:['canCreateEntry'=>$actor->can('create',GstEntry::class)]);
    }
}
