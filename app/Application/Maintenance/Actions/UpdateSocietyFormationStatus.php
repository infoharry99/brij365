<?php
namespace App\Application\Maintenance\Actions; use App\Application\Maintenance\Data\MaintenanceCommandData; use App\Models\SocietyFormation; use App\Services\Maintenance\MaintenanceSocietyService;
final class UpdateSocietyFormationStatus { public function __construct(private readonly MaintenanceSocietyService $s) {} public function execute(SocietyFormation $m,MaintenanceCommandData $c): SocietyFormation { return $this->s->updateSocietyFormationStatus($m,$c->attributes,$c->actor,$c->request); } }
