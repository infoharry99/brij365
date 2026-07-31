<?php
namespace App\Application\Maintenance\Actions;
use App\Application\Maintenance\Data\MaintenanceDueWorkspaceData; use App\Domain\Maintenance\Services\MaintenanceRegister; use App\Models\MaintenanceDue; use App\Models\User;
final class ListMaintenanceDueWorkspace { public function __construct(private readonly MaintenanceRegister $r) {} public function execute(User $a,array $f): MaintenanceDueWorkspaceData { $b=$this->r->isBuyer($a); return new MaintenanceDueWorkspaceData($this->r->dues($a,$f),$f,$b?collect():$this->r->projects($a),$b?collect():$this->r->bookings($a),$b?collect():$this->r->customers($a),['due'=>'Due','overdue'=>'Overdue','paid'=>'Paid','cancelled'=>'Cancelled'],['canCreateDue'=>$a->can('create',MaintenanceDue::class)]); } }
