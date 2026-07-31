<?php
namespace App\Application\Legal\Actions;
use App\Application\Legal\Data\LegalCommandData; use App\Models\ReraRegistration; use App\Services\Legal\LegalComplianceService;
final class CreateReraRegistration { public function __construct(private readonly LegalComplianceService $legal) {} public function execute(LegalCommandData $c): ReraRegistration { return $this->legal->createReraRegistration($c->attributes,$c->actor,$c->request); } }
