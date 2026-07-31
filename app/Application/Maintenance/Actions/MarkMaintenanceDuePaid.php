<?php
namespace App\Application\Maintenance\Actions; use App\Application\Maintenance\Data\MaintenanceCommandData; use App\Models\MaintenanceDue; use App\Services\Maintenance\MaintenanceSocietyService;
final class MarkMaintenanceDuePaid { public function __construct(private readonly MaintenanceSocietyService $s) {} public function execute(MaintenanceDue $m,MaintenanceCommandData $c): MaintenanceDue { return $this->s->markMaintenanceDuePaid($m,$c->attributes,$c->actor,$c->request); } }
