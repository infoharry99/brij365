<?php

namespace Tests\Feature;

use App\Application\AfterSales\Data\AfterSalesCommandData;
use App\Application\AfterSales\Data\CloseServiceTicketData;
use App\Application\AfterSales\Data\MaintenanceWorkOrderWorkspaceData;
use App\Application\AfterSales\Data\ServiceTicketWorkspaceData;
use ReflectionClass;
use Tests\TestCase;

class AfterSalesApplicationLayerTest extends TestCase
{
    public function test_after_sales_command_and_workspace_data_are_immutable(): void
    {
        foreach ([AfterSalesCommandData::class, CloseServiceTicketData::class, ServiceTicketWorkspaceData::class, MaintenanceWorkOrderWorkspaceData::class] as $class) {
            $this->assertTrue((new ReflectionClass($class))->isReadOnly(), $class.' must be readonly.');
        }
    }

    public function test_after_sales_controller_uses_focused_actions_without_queries_or_services(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/AfterSales/AfterSalesController.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('App\\Application\\AfterSales\\Actions', $source);
        $this->assertStringNotContainsString('::query()', $source);
        $this->assertStringNotContainsString('App\\Services\\', $source);
    }
}
