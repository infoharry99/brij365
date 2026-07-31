<?php
namespace App\Application\Legal\Actions;
use App\Application\Legal\Data\LegalCommandData; use App\Models\ComplianceObligation; use App\Services\Legal\LegalComplianceService;
final class CompleteComplianceObligation { public function __construct(private readonly LegalComplianceService $legal) {} public function execute(ComplianceObligation $o,LegalCommandData $c): ComplianceObligation { return $this->legal->completeComplianceObligation($o,$c->attributes,$c->actor,$c->request); } }
