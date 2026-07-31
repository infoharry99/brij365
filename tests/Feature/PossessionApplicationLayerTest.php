<?php
namespace Tests\Feature;
use App\Application\Possession\Data\HandoverSnagWorkspaceData; use App\Application\Possession\Data\PossessionCommandData; use App\Application\Possession\Data\PossessionHandoverWorkspaceData; use ReflectionClass; use Tests\TestCase;
class PossessionApplicationLayerTest extends TestCase
{
    public function test_possession_command_and_workspace_data_are_immutable(): void { foreach([PossessionCommandData::class,PossessionHandoverWorkspaceData::class,HandoverSnagWorkspaceData::class] as $class)$this->assertTrue((new ReflectionClass($class))->isReadOnly(),$class.' must be readonly.'); }
    public function test_possession_controller_uses_focused_actions(): void { $s=file_get_contents(app_path('Http/Controllers/Possession/PossessionHandoverController.php')); $this->assertIsString($s); $this->assertStringContainsString('App\\Application\\Possession\\Actions',$s); $this->assertStringNotContainsString('::query()',$s); $this->assertStringNotContainsString('App\\Services\\',$s); }
}
