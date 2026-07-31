<?php

namespace Tests\Feature;

use App\Application\Inventory\Data\InventoryCommandData;
use App\Application\Inventory\Data\UnitAvailabilityExportData;
use App\Application\Inventory\Data\UnitInventoryWorkspaceData;
use App\Application\Inventory\Data\UnitPricingWorkspaceData;
use App\Application\Projects\Data\ProjectCommandData;
use App\Application\Projects\Data\ProjectWorkspaceData;
use ReflectionClass;
use Tests\TestCase;

class ProjectInventoryApplicationLayerTest extends TestCase
{
    public function test_project_and_inventory_page_and_command_data_are_immutable(): void
    {
        foreach ([
            ProjectCommandData::class,
            ProjectWorkspaceData::class,
            InventoryCommandData::class,
            UnitInventoryWorkspaceData::class,
            UnitPricingWorkspaceData::class,
            UnitAvailabilityExportData::class,
        ] as $class) {
            $this->assertTrue((new ReflectionClass($class))->isReadOnly(), $class.' must remain immutable.');
        }
    }

    public function test_project_inventory_and_pricing_controllers_use_focused_actions(): void
    {
        $projects = file_get_contents(app_path('Http/Controllers/Projects/ProjectController.php'));
        $units = file_get_contents(app_path('Http/Controllers/Inventory/ProjectUnitController.php'));
        $pricing = file_get_contents(app_path('Http/Controllers/Inventory/UnitPricingController.php'));

        $this->assertStringContainsString('ListProjectWorkspace $action', $projects);
        $this->assertStringContainsString('CreateProject $action', $projects);
        $this->assertStringContainsString('AssignProjectTeamMember $action', $projects);
        $this->assertStringContainsString('RevokeProjectTeamAssignmentRequest $request', $projects);
        $this->assertStringNotContainsString('ProjectManagementService $service', $projects);

        $this->assertStringContainsString('ListUnitInventoryWorkspace $action', $units);
        $this->assertStringContainsString('ExportUnitAvailability $action', $units);
        $this->assertStringNotContainsString('CompanyScopeService $companyScope', $units);

        $this->assertStringContainsString('ListUnitPricingWorkspace $action', $pricing);
        $this->assertStringContainsString('CreateUnitPriceVersion $action', $pricing);
        $this->assertStringContainsString('ApproveUnitPriceVersion $action', $pricing);
        $this->assertStringNotContainsString('UnitPricingService $pricing', $pricing);
    }
}
