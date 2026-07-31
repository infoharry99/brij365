<?php

namespace Tests\Feature;

use ReflectionClass;
use Tests\TestCase;

class PortalApplicationLayerTest extends TestCase
{
    public function test_portal_workspace_data_is_immutable(): void
    {
        foreach ([
            \App\Application\Buyer\Data\BuyerPortalWorkspaceData::class,
            \App\Application\Buyer\Data\BuyerPortalSummaryData::class,
            \App\Application\Partner\Data\PartnerPortalWorkspaceData::class,
            \App\Application\Partner\Data\PartnerPortalSummaryData::class,
        ] as $class) {
            $this->assertTrue((new ReflectionClass($class))->isReadOnly(), $class.' must be readonly.');
        }
    }

    public function test_portal_controllers_use_actions_without_direct_queries_or_services(): void
    {
        foreach ([
            app_path('Http/Controllers/Buyer/BuyerPortalController.php'),
            app_path('Http/Controllers/Partner/PartnerDashboardController.php'),
            app_path('Http/Controllers/Partner/PartnerLeadController.php'),
            app_path('Http/Controllers/Partner/PartnerBookingController.php'),
        ] as $path) {
            $source = file_get_contents($path);
            $this->assertIsString($source);
            $this->assertStringNotContainsString('::query()', $source, $path);
            $this->assertStringNotContainsString('App\\Services\\', $source, $path);
        }
    }
}
