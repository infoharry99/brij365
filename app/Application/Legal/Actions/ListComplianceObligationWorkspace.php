<?php
namespace App\Application\Legal\Actions;
use App\Application\Legal\Data\ComplianceObligationWorkspaceData;
use App\Domain\Legal\Services\LegalComplianceRegister;
use App\Models\ComplianceObligation;
use App\Models\User;
final class ListComplianceObligationWorkspace
{
    public function __construct(private readonly LegalComplianceRegister $register) {}
    public function execute(User $actor,array $filters): ComplianceObligationWorkspaceData { return new ComplianceObligationWorkspaceData($this->register->obligations($actor,$filters),$filters,$this->register->projects($actor),$this->register->assignees($actor),['open'=>'Open','completed'=>'Completed'],['one_time'=>'One Time','monthly'=>'Monthly','quarterly'=>'Quarterly','half_yearly'=>'Half Yearly','annual'=>'Annual'],['low'=>'Low','normal'=>'Normal','high'=>'High','critical'=>'Critical'],['canCreateObligation'=>$actor->can('create',ComplianceObligation::class)]); }
}
