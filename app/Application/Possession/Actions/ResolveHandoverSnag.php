<?php
namespace App\Application\Possession\Actions;
use App\Application\Possession\Data\PossessionCommandData; use App\Models\HandoverSnag; use App\Services\Possession\PossessionHandoverService;
final class ResolveHandoverSnag { public function __construct(private readonly PossessionHandoverService $service) {} public function execute(HandoverSnag $s,PossessionCommandData $c): HandoverSnag { return $this->service->resolveSnag($s,$c->attributes['resolution_notes'],$c->actor,$c->request); } }
