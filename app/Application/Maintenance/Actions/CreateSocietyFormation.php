<?php
namespace App\Application\Maintenance\Actions; use App\Application\Maintenance\Data\MaintenanceCommandData; use App\Models\SocietyFormation; use App\Services\Maintenance\MaintenanceSocietyService;
final class CreateSocietyFormation { public function __construct(private readonly MaintenanceSocietyService $s) {} public function execute(MaintenanceCommandData $c): SocietyFormation { return $this->s->createSocietyFormation($c->attributes,$c->actor,$c->request); } }
