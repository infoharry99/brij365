<?php
namespace App\Application\Governance\Actions;
use App\Application\Governance\Data\GovernanceCommandData;
use App\Domain\Governance\Services\ReportSubscriptionManager;
use App\Models\ReportPin;
final class PinReport { public function __construct(private readonly ReportSubscriptionManager $manager) {} public function execute(GovernanceCommandData $command): ReportPin { return $this->manager->pin($command->actor,$command->attributes,$command->request); } }
