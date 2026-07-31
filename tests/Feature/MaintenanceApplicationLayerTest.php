<?php
namespace Tests\Feature;
use App\Application\Maintenance\Data\CommonAreaHandoverWorkspaceData; use App\Application\Maintenance\Data\MaintenanceCommandData; use App\Application\Maintenance\Data\MaintenanceDueWorkspaceData; use App\Application\Maintenance\Data\SocietyWorkspaceData; use ReflectionClass; use Tests\TestCase;
class MaintenanceApplicationLayerTest extends TestCase
{
    public function test_maintenance_command_and_workspace_data_are_immutable(): void { foreach([MaintenanceCommandData::class,SocietyWorkspaceData::class,CommonAreaHandoverWorkspaceData::class,MaintenanceDueWorkspaceData::class] as $class)$this->assertTrue((new ReflectionClass($class))->isReadOnly(),$class.' must be readonly.'); }
    public function test_maintenance_controller_uses_focused_actions(): void { $s=file_get_contents(app_path('Http/Controllers/Maintenance/MaintenanceSocietyController.php')); $this->assertIsString($s); $this->assertStringContainsString('App\\Application\\Maintenance\\Actions',$s); $this->assertStringNotContainsString('::query()',$s); $this->assertStringNotContainsString('App\\Services\\',$s); }
}
