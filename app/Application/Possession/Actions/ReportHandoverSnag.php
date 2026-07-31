<?php
namespace App\Application\Possession\Actions;
use App\Application\Possession\Data\PossessionCommandData; use App\Models\HandoverSnag; use App\Services\Possession\PossessionHandoverService;
final class ReportHandoverSnag { public function __construct(private readonly PossessionHandoverService $service) {} public function execute(PossessionCommandData $c): HandoverSnag { return $this->service->reportSnag($c->attributes,$c->actor,$c->request); } }
