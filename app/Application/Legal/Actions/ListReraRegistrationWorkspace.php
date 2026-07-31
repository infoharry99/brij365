<?php
namespace App\Application\Legal\Actions;
use App\Application\Legal\Data\ReraRegistrationWorkspaceData;
use App\Domain\Legal\Services\LegalComplianceRegister;
use App\Models\ReraRegistration;
use App\Models\User;
final class ListReraRegistrationWorkspace
{
    public function __construct(private readonly LegalComplianceRegister $register) {}
    public function execute(User $actor,array $filters): ReraRegistrationWorkspaceData { return new ReraRegistrationWorkspaceData($this->register->registrations($actor,$filters),$filters,$this->register->projects($actor),['submitted'=>'Submitted','verified'=>'Verified'],['canCreateRegistration'=>$actor->can('create',ReraRegistration::class)]); }
}
