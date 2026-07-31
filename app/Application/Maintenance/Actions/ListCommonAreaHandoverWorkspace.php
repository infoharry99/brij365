<?php
namespace App\Application\Maintenance\Actions;
use App\Application\Maintenance\Data\CommonAreaHandoverWorkspaceData; use App\Domain\Maintenance\Services\MaintenanceRegister; use App\Models\User;
final class ListCommonAreaHandoverWorkspace { public function __construct(private readonly MaintenanceRegister $r) {} public function execute(User $a,array $f): CommonAreaHandoverWorkspaceData { return new CommonAreaHandoverWorkspaceData($this->r->items($a,$f),$f,$this->r->projects($a),$this->r->societyOptions($a),['pending'=>'Pending','in_progress'=>'In Progress','pending_snags'=>'Pending Snags','complete'=>'Complete']); } }
