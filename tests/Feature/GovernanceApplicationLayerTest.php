<?php

namespace Tests\Feature;

use ReflectionClass;
use Tests\TestCase;

class GovernanceApplicationLayerTest extends TestCase
{
    public function test_governance_application_data_is_immutable(): void
    {
        foreach ([
            \App\Application\Governance\Data\AuditTrailPageData::class,
            \App\Application\Governance\Data\AuditTrailExportData::class,
            \App\Application\Governance\Data\GovernanceCommandData::class,
            \App\Application\Governance\Data\ManagementSummaryData::class,
            \App\Application\Governance\Data\ReportExportData::class,
            \App\Application\Approvals\Data\ApprovalCenterExportData::class,
        ] as $class) {
            $this->assertTrue((new ReflectionClass($class))->isReadOnly(), $class.' must be readonly.');
        }
    }

    public function test_governance_controllers_delegate_queries_and_transactions_to_actions(): void
    {
        foreach ([
            app_path('Http/Controllers/Governance/AuditTrailController.php'),
            app_path('Http/Controllers/Governance/ManagementReportController.php'),
            app_path('Http/Controllers/Builder360/ApprovalCenterController.php'),
        ] as $path) {
            $source = file_get_contents($path);
            $this->assertIsString($source);
            $this->assertStringNotContainsString('::query()', $source, $path);
            $this->assertStringNotContainsString('DB::transaction', $source, $path);
            $this->assertStringNotContainsString('App\\Services\\', $source, $path);
        }
    }
}
