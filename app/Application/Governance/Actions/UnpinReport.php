<?php
namespace App\Application\Governance\Actions;
use App\Application\Governance\Data\GovernanceCommandData;
use App\Domain\Governance\Services\ReportSubscriptionManager;
use App\Models\ReportPin;
final class UnpinReport { public function __construct(private readonly ReportSubscriptionManager $manager) {} public function execute(ReportPin $pin,GovernanceCommandData $command): void { $this->manager->unpin($pin,$command->actor,$command->request); } }
