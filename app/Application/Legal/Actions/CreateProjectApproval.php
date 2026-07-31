<?php
namespace App\Application\Legal\Actions;
use App\Application\Legal\Data\LegalCommandData; use App\Models\ProjectApproval; use App\Services\Legal\LegalComplianceService;
final class CreateProjectApproval { public function __construct(private readonly LegalComplianceService $legal) {} public function execute(LegalCommandData $c): ProjectApproval { return $this->legal->createProjectApproval($c->attributes,$c->actor,$c->request); } }
