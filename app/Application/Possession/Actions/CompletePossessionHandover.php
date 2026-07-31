<?php
namespace App\Application\Possession\Actions;
use App\Application\Possession\Data\PossessionCommandData; use App\Models\PossessionHandover; use App\Services\Possession\PossessionHandoverService;
final class CompletePossessionHandover { public function __construct(private readonly PossessionHandoverService $service) {} public function execute(PossessionHandover $h,PossessionCommandData $c): PossessionHandover { return $this->service->complete($h,$c->attributes,$c->actor,$c->request); } }
