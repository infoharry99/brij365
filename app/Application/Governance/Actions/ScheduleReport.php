<?php
namespace App\Application\Governance\Actions;
use App\Application\Governance\Data\GovernanceCommandData;
use App\Domain\Governance\Services\ReportSubscriptionManager;
use App\Models\ReportSchedule;
final class ScheduleReport { public function __construct(private readonly ReportSubscriptionManager $manager) {} public function execute(GovernanceCommandData $command): ReportSchedule { return $this->manager->schedule($command->actor,$command->attributes,$command->request); } }
