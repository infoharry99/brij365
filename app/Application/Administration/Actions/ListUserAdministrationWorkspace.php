<?php
namespace App\Application\Administration\Actions;
use App\Application\Administration\Data\AdministrationWorkspaceData;
use App\Domain\Administration\Services\AdministrationRegister;
use App\Models\User;
final class ListUserAdministrationWorkspace {
    public function __construct(private readonly AdministrationRegister $register) {}
    public function execute(User $actor,array $filters): AdministrationWorkspaceData { return new AdministrationWorkspaceData('users',$this->register->users($actor,$filters),$filters,$this->register->companyOptions($actor),$this->register->assignableRoles($actor),statuses:['active'=>'Active','inactive'=>'Inactive','suspended'=>'Suspended'],canCreate:$actor->can('create',User::class)); }
}
