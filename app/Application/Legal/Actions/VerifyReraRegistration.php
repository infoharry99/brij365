<?php
namespace App\Application\Legal\Actions;
use App\Application\Legal\Data\LegalCommandData; use App\Models\ReraRegistration; use App\Services\Legal\LegalComplianceService;
final class VerifyReraRegistration { public function __construct(private readonly LegalComplianceService $legal) {} public function execute(ReraRegistration $r,LegalCommandData $c): ReraRegistration { return $this->legal->verifyReraRegistration($r,$c->attributes,$c->actor,$c->request); } }
