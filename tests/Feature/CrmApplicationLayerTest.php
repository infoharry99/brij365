<?php

namespace Tests\Feature;

use App\Application\Crm\Data\CrmCommandData;
use App\Application\Crm\Data\LeadActivityWorkspaceData;
use App\Application\Crm\Data\LeadQualificationWorkspaceData;
use App\Application\Crm\Data\LeadWorkspaceData;
use App\Application\Crm\Data\MarketingCampaignWorkspaceData;
use App\Application\Crm\Data\ProspectInquiryWorkspaceData;
use App\Application\Crm\Data\SalesAnalyticsWorkspaceData;
use App\Application\Crm\Data\SiteVisitWorkspaceData;
use App\Application\Sales\Data\BookingWorkspaceData;
use App\Application\Sales\Data\SalesCommandData;
use ReflectionClass;
use Tests\TestCase;

class CrmApplicationLayerTest extends TestCase
{
    public function test_crm_commands_and_page_data_are_immutable(): void
    {
        foreach ([
            CrmCommandData::class,
            LeadWorkspaceData::class,
            LeadQualificationWorkspaceData::class,
            SiteVisitWorkspaceData::class,
            ProspectInquiryWorkspaceData::class,
            MarketingCampaignWorkspaceData::class,
            LeadActivityWorkspaceData::class,
            SalesAnalyticsWorkspaceData::class,
            SalesCommandData::class,
            BookingWorkspaceData::class,
        ] as $class) {
            $this->assertTrue((new ReflectionClass($class))->isReadOnly(), $class.' must remain immutable.');
        }
    }

    public function test_lead_and_engagement_controllers_use_focused_actions(): void
    {
        $leadController = file_get_contents(app_path('Http/Controllers/Crm/LeadController.php'));
        $engagementController = file_get_contents(app_path('Http/Controllers/Crm/LeadEngagementController.php'));

        $this->assertIsString($leadController);
        $this->assertIsString($engagementController);
        $this->assertStringContainsString('ListLeadWorkspace $action', $leadController);
        $this->assertStringContainsString('CreateLead $action', $leadController);
        $this->assertStringNotContainsString('LeadService $leadService', $leadController);
        $this->assertStringContainsString('ListLeadQualificationWorkspace $action', $engagementController);
        $this->assertStringContainsString('ListSiteVisitWorkspace $action', $engagementController);
        $this->assertStringContainsString('ScheduleSiteVisit $action', $engagementController);
        $this->assertStringNotContainsString('LeadEngagementService $service', $engagementController);
    }

    public function test_remaining_crm_and_sales_controllers_use_focused_actions(): void
    {
        $prospects = file_get_contents(app_path('Http/Controllers/Crm/ProspectInquiryController.php'));
        $marketing = file_get_contents(app_path('Http/Controllers/Crm/MarketingCampaignController.php'));
        $analytics = file_get_contents(app_path('Http/Controllers/Crm/SalesAnalyticsController.php'));
        $bookings = file_get_contents(app_path('Http/Controllers/Sales/BookingController.php'));
        $quotes = file_get_contents(app_path('Http/Controllers/Sales/BookingQuoteController.php'));

        $this->assertStringContainsString('ListProspectInquiryWorkspace $action', $prospects);
        $this->assertStringContainsString('ListMarketingCampaignWorkspace $action', $marketing);
        $this->assertStringContainsString('ListLeadActivityWorkspace $action', $marketing);
        $this->assertStringContainsString('ViewSalesAnalytics $action', $analytics);
        $this->assertStringContainsString('ListBookingWorkspace $action', $bookings);
        $this->assertStringContainsString('CreateBooking $action', $bookings);
        $this->assertStringContainsString('QuoteBooking $action', $quotes);
        $this->assertStringNotContainsString('CompanyScopeService $companyScope', $bookings);
        $this->assertStringNotContainsString('MarketingCampaignService $service', $marketing);
    }
}
