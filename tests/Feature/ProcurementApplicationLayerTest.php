<?php

namespace Tests\Feature;

use App\Application\Procurement\Data\ProcurementCommandData;
use App\Application\Procurement\Data\ProcurementWorkspaceData;
use ReflectionClass;
use Tests\TestCase;

class ProcurementApplicationLayerTest extends TestCase
{
    public function test_procurement_command_and_workspace_data_are_immutable(): void
    {
        foreach ([ProcurementCommandData::class, ProcurementWorkspaceData::class] as $class) {
            $this->assertTrue((new ReflectionClass($class))->isReadOnly(), $class.' must remain immutable.');
        }
    }

    public function test_procurement_controller_uses_focused_actions_without_queries_or_workflow_services(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Procurement/ProcurementController.php'));

        foreach ([
            'ListProcurementWorkspace $workspace', 'ListVendors $list', 'CreateVendor $create',
            'ListPurchaseRequisitions $list', 'SubmitPurchaseRequisition $submit',
            'CompareProcurementQuotes $compare', 'ListPurchaseOrders $list',
            'CreatePurchaseOrder $create', 'ListGoodsReceipts $list', 'ReceiveGoods $receive',
            'ListStockItems $list', 'IssueStock $issue', 'ReturnStock $return', 'TransferStock $transfer',
        ] as $boundary) {
            $this->assertStringContainsString($boundary, $controller);
        }

        $this->assertStringNotContainsString('ProcurementService $service', $controller);
        $this->assertStringNotContainsString('CompanyScopeService $companyScope', $controller);
        $this->assertStringNotContainsString('::query()', $controller);
    }
}
