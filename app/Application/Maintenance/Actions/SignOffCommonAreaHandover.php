<?php
namespace App\Application\Maintenance\Actions; use App\Application\Maintenance\Data\MaintenanceCommandData; use App\Models\CommonAreaHandoverItem; use App\Services\Maintenance\MaintenanceSocietyService;
final class SignOffCommonAreaHandover { public function __construct(private readonly MaintenanceSocietyService $s) {} public function execute(CommonAreaHandoverItem $m,MaintenanceCommandData $c): CommonAreaHandoverItem { return $this->s->signOffCommonAreaHandoverItem($m,$c->attributes,$c->actor,$c->request); } }
