<?php
namespace App\Application\Maintenance\Actions; use App\Application\Maintenance\Data\MaintenanceCommandData; use App\Models\MaintenanceDue; use App\Services\Maintenance\MaintenanceSocietyService;
final class RaiseMaintenanceDue { public function __construct(private readonly MaintenanceSocietyService $s) {} public function execute(MaintenanceCommandData $c): MaintenanceDue { return $this->s->raiseMaintenanceDue($c->attributes,$c->actor,$c->request); } }
