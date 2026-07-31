<?php
namespace App\Application\Legal\Actions;
use App\Application\Legal\Data\LegalCommandData; use App\Models\ComplianceObligation; use App\Services\Legal\LegalComplianceService;
final class CreateComplianceObligation { public function __construct(private readonly LegalComplianceService $legal) {} public function execute(LegalCommandData $c): ComplianceObligation { return $this->legal->createComplianceObligation($c->attributes,$c->actor,$c->request); } }
