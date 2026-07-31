<?php
namespace App\Application\Legal\Actions;
use App\Application\Legal\Data\LegalCommandData; use App\Models\ProjectApproval; use App\Services\Legal\LegalComplianceService;
final class VerifyProjectApproval { public function __construct(private readonly LegalComplianceService $legal) {} public function execute(ProjectApproval $a,LegalCommandData $c): ProjectApproval { return $this->legal->verifyProjectApproval($a,$c->attributes,$c->actor,$c->request); } }
