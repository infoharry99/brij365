<?php
namespace App\Application\Legal\Actions;
use App\Application\Legal\Data\ProjectApprovalWorkspaceData;
use App\Domain\Legal\Services\LegalComplianceRegister;
use App\Models\ProjectApproval;
use App\Models\User;
final class ListProjectApprovalWorkspace
{
    public function __construct(private readonly LegalComplianceRegister $register) {}
    public function execute(User $actor,array $filters): ProjectApprovalWorkspaceData { return new ProjectApprovalWorkspaceData($this->register->approvals($actor,$filters),$filters,$this->register->projects($actor),['applied'=>'Applied','approved'=>'Approved','verified'=>'Verified'],['Commencement Certificate'=>'Commencement Certificate','Fire NOC'=>'Fire NOC','Environment Clearance'=>'Environment Clearance','Occupation Certificate'=>'Occupation Certificate','Municipal Approval'=>'Municipal Approval','Utility NOC'=>'Utility NOC'],['canCreateApproval'=>$actor->can('create',ProjectApproval::class)]); }
}
