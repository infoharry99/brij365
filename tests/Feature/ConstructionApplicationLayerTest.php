<?php

namespace Tests\Feature;

use App\Application\Construction\Data\ConstructionCommandData;
use App\Application\Construction\Data\ConstructionProgressWorkspaceData;
use ReflectionClass;
use Tests\TestCase;

class ConstructionApplicationLayerTest extends TestCase
{
    public function test_construction_command_and_workspace_data_are_immutable(): void
    {
        foreach ([ConstructionCommandData::class, ConstructionProgressWorkspaceData::class] as $class) {
            $this->assertTrue((new ReflectionClass($class))->isReadOnly(), $class.' must remain immutable.');
        }
    }

    public function test_construction_controller_uses_focused_actions_instead_of_queries_or_services(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Construction/ConstructionController.php'));

        $this->assertStringContainsString('ListConstructionProgressWorkspace $workspace', $controller);
        $this->assertStringContainsString('CreateConstructionMilestone $create', $controller);
        $this->assertStringContainsString('SubmitDailyProgressReport $submit', $controller);
        $this->assertStringContainsString('ListBoqItems $list', $controller);
        $this->assertStringContainsString('SubmitContractorMeasurement $submit', $controller);
        $this->assertStringContainsString('CreateContractorBill $create', $controller);
        $this->assertStringNotContainsString('CompanyScopeService $companyScope', $controller);
        $this->assertStringNotContainsString('ConstructionService $service', $controller);
        $this->assertStringNotContainsString('::query()', $controller);
    }
}
