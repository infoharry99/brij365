<?php

namespace Tests\Feature;

use ReflectionClass;
use Tests\TestCase;

class AdministrationApplicationLayerTest extends TestCase
{
    public function test_administration_and_settings_workspace_data_are_immutable(): void
    {
        foreach ([
            \App\Application\Administration\Data\AdministrationWorkspaceData::class,
            \App\Application\Settings\Data\SettingsWorkspaceData::class,
        ] as $class) {
            $this->assertTrue((new ReflectionClass($class))->isReadOnly(), $class.' must be readonly.');
        }
    }

    public function test_administration_and_settings_indexes_use_actions_without_queries(): void
    {
        foreach ([
            app_path('Http/Controllers/Admin/UserAdministrationController.php'),
            app_path('Http/Controllers/Admin/RoleAdministrationController.php'),
            app_path('Http/Controllers/Admin/CompanyAdministrationController.php'),
            app_path('Http/Controllers/Settings/SystemSettingController.php'),
            app_path('Http/Controllers/Settings/DataImportController.php'),
        ] as $path) {
            $source = file_get_contents($path);
            $this->assertIsString($source);
            $this->assertStringNotContainsString('::query()', $source, $path);
        }
    }
}
