<?php
namespace App\Application\Administration\Actions;
use App\Application\Administration\Data\AdministrationWorkspaceData;
use App\Domain\Administration\Services\AdministrationRegister;
final class ListCompanyAdministrationWorkspace { public function __construct(private readonly AdministrationRegister $register) {} public function execute(): AdministrationWorkspaceData { return new AdministrationWorkspaceData('companies',$this->register->companies()); } }
