<?php
namespace App\Application\Possession\Actions;
use App\Application\Possession\Data\PossessionCommandData; use App\Models\PossessionHandover; use App\Services\Possession\PossessionHandoverService;
final class InitiatePossessionHandover { public function __construct(private readonly PossessionHandoverService $service) {} public function execute(PossessionCommandData $c): PossessionHandover { return $this->service->initiate($c->attributes,$c->actor,$c->request); } }
