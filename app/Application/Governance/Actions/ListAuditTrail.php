<?php
namespace App\Application\Governance\Actions;
use App\Application\Governance\Data\AuditTrailPageData;
use App\Domain\Governance\Services\AuditTrailRegister;
use App\Models\User;
final class ListAuditTrail {
 public function __construct(private readonly AuditTrailRegister $register) {}
 public function execute(User $actor,array $filters): AuditTrailPageData { return new AuditTrailPageData($this->register->events($actor,$filters),$filters,$this->register->eventTypes($actor),$this->register->auditableTypes($actor),$this->register->users($actor),['GET'=>'GET','POST'=>'POST','PATCH'=>'PATCH','PUT'=>'PUT','DELETE'=>'DELETE']); }
}
