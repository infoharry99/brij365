<?php
namespace App\Application\Administration\Actions;
use App\Application\Administration\Data\AdministrationWorkspaceData;
use App\Domain\Administration\Services\AdministrationRegister;
use App\Models\Role;
use App\Models\User;
final class ListRoleAdministrationWorkspace {
    public function __construct(private readonly AdministrationRegister $register) {}
    public function execute(User $actor,array $filters): AdministrationWorkspaceData { return new AdministrationWorkspaceData('roles',$this->register->roles($filters),$filters,permissions:$this->register->permissionCatalog($actor),scopeLevels:['global'=>'Global','company'=>'Company','department'=>'Department','project'=>'Project','self'=>'Self-service','readonly'=>'Read only','partner'=>'Partner'],canCreate:$actor->can('create',Role::class)); }
}
