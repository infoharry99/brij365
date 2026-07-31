<?php

namespace Tests\Feature;

use App\Application\Finance\Data\CollectionReceiptWorkspaceData;
use App\Application\Finance\Data\FinanceCommandData;
use App\Application\Finance\Data\FinanceDashboardPageData;
use App\Application\Finance\Data\FinancialVoucherWorkspaceData;
use App\Application\Finance\Data\GstEntryWorkspaceData;
use App\Application\Finance\Data\GstReturnPeriodWorkspaceData;
use App\Application\Finance\Data\PaymentRequestWorkspaceData;
use ReflectionClass;
use Tests\TestCase;

class FinanceApplicationLayerTest extends TestCase
{
    public function test_finance_command_and_workspace_data_are_immutable(): void
    {
        foreach ([
            FinanceCommandData::class, FinanceDashboardPageData::class, CollectionReceiptWorkspaceData::class,
            FinancialVoucherWorkspaceData::class, PaymentRequestWorkspaceData::class,
            GstEntryWorkspaceData::class, GstReturnPeriodWorkspaceData::class,
        ] as $class) {
            $this->assertTrue((new ReflectionClass($class))->isReadOnly(), $class.' must be readonly.');
        }
    }

    public function test_finance_dashboard_and_collection_controllers_use_focused_actions(): void
    {
        foreach ([
            app_path('Http/Controllers/Finance/FinanceDashboardController.php'),
            app_path('Http/Controllers/Finance/CollectionReceiptController.php'),
            app_path('Http/Controllers/Finance/FinancialVoucherController.php'),
            app_path('Http/Controllers/Finance/PaymentRequestController.php'),
            app_path('Http/Controllers/Finance/GstComplianceController.php'),
        ] as $path) {
            $source = file_get_contents($path);
            $this->assertIsString($source);
            $this->assertStringContainsString('App\\Application\\Finance\\Actions', $source);
            $this->assertStringNotContainsString('::query()', $source);
            $this->assertStringNotContainsString('App\\Services\\', $source);
        }
    }
}
