<?php
namespace App\Application\Governance\Actions;
use App\Application\Governance\Data\GovernanceCommandData;
use App\Domain\Governance\Services\ReportSubscriptionManager;
use App\Models\ReportSchedule;
final class ArchiveReportSchedule { public function __construct(private readonly ReportSubscriptionManager $manager) {} public function execute(ReportSchedule $schedule,GovernanceCommandData $command): ReportSchedule { return $this->manager->archive($schedule,$command->actor,$command->request); } }
