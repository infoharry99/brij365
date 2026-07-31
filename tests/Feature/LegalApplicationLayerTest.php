<?php
namespace Tests\Feature;
use App\Application\Legal\Data\ComplianceObligationWorkspaceData;
use App\Application\Legal\Data\LegalCommandData;
use App\Application\Legal\Data\ProjectApprovalWorkspaceData;
use App\Application\Legal\Data\ReraRegistrationWorkspaceData;
use ReflectionClass;
use Tests\TestCase;
class LegalApplicationLayerTest extends TestCase
{
    public function test_legal_command_and_workspace_data_are_immutable(): void
    {
        foreach ([LegalCommandData::class,ReraRegistrationWorkspaceData::class,ProjectApprovalWorkspaceData::class,ComplianceObligationWorkspaceData::class] as $class) $this->assertTrue((new ReflectionClass($class))->isReadOnly(),$class.' must be readonly.');
    }
    public function test_legal_controller_uses_focused_actions_without_queries_or_services(): void
    {
        $source=file_get_contents(app_path('Http/Controllers/Legal/LegalComplianceController.php'));
        $this->assertIsString($source); $this->assertStringContainsString('App\\Application\\Legal\\Actions',$source); $this->assertStringNotContainsString('::query()',$source); $this->assertStringNotContainsString('App\\Services\\',$source);
    }
}
