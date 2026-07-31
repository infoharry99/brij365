<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\ManagedDocument;
use App\Models\Role;
use App\Models\User;
use App\Services\Builder360\Builder360Bootstrap;
use App\Services\Partner\PartnerScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PartnerPortalScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_register_routes_render_native_blade_workspaces(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        foreach ([
            'partner.leads.index' => 'My Leads',
            'partner.bookings.index' => 'My Bookings',
        ] as $route => $heading) {
            $this->actingAs($partner)->get(route($route))
                ->assertOk()
                ->assertSee($heading)
                ->assertSee('aria-label="Partner portal navigation"', false)
                ->assertDontSee('id="root"', false);
        }
    }

    public function test_partner_summary_returns_scoped_portal_sections(): void
    {
        $this->seed();

        $partnerUser = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $response = $this->actingAs($partnerUser)
            ->getJson(route('partner.summary', ['limit' => 5]))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'scope' => [
                        'partner_ids',
                        'partners' => [
                            '*' => ['id', 'code', 'name', 'type', 'status'],
                        ],
                    ],
                    'metrics' => [
                        'leads',
                        'open_leads',
                        'site_visits',
                        'bookings',
                        'open_collection_amount',
                        'approved_commission_amount',
                    ],
                    'lead_stage_summary',
                    'my_leads',
                    'site_visits',
                    'bookings',
                    'collections_follow_up',
                    'commission_summary' => [
                        'total_items',
                        'approved_amount',
                        'pending_amount',
                        'paid_amount',
                        'items',
                    ],
                    'documents',
                ],
            ]);

        $payload = $response->json('data');

        $this->assertSame(['CP-001'], array_column($payload['scope']['partners'], 'code'));
        $this->assertGreaterThan(0, $payload['metrics']['leads']);
        $this->assertGreaterThan(0, $payload['metrics']['site_visits']);
        $this->assertGreaterThan(0, $payload['metrics']['bookings']);
        $this->assertLessThanOrEqual(5, count($payload['my_leads']));
        $this->assertLessThanOrEqual(5, count($payload['site_visits']));
        $this->assertLessThanOrEqual(5, count($payload['bookings']));
        $this->assertSame('/documents/'.$payload['documents'][0]['id'].'/download', $payload['documents'][0]['download_url']);
    }

    public function test_channel_partner_can_use_native_blade_partner_portal(): void
    {
        $this->seed();

        $partnerUser = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $this->actingAs($partnerUser)
            ->get(route('partner.summary', ['limit' => 5]))
            ->assertOk()
            ->assertSee('aria-label="Primary navigation"', false)
            ->assertSee('Partner Dashboard')
            ->assertDontSee('Lead Management')
            ->assertDontSee('Document Mgmt')
            ->assertSee('Secure partner workspace')
            ->assertDontSee('Native Laravel Blade partner workspace')
            ->assertSee('CP-001')
            ->assertSee('Bafna Realty Network')
            ->assertSee('LD-1001')
            ->assertSee('BK-1001')
            ->assertSee('Commission summary')
            ->assertSee('DOC-1002')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);
    }

    public function test_executive_partner_broker_can_use_native_blade_partner_portal_with_own_scope(): void
    {
        $this->seed();

        $brokerUser = User::where('email', 'farhan.shaikh@partners.builder360.test')->firstOrFail();

        $this->actingAs($brokerUser)
            ->get(route('partner.summary'))
            ->assertOk()
            ->assertSee('aria-label="Primary navigation"', false)
            ->assertSee('Partner Dashboard')
            ->assertDontSee('Lead Management')
            ->assertDontSee('Document Mgmt')
            ->assertSee('Secure partner workspace')
            ->assertDontSee('Native Laravel Blade partner workspace')
            ->assertSee('BR-001')
            ->assertSee('Shaikh Executive Brokers')
            ->assertSee('No leads found.')
            ->assertDontSee('CP-001')
            ->assertDontSee('Bafna Realty Network')
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);
    }

    public function test_partner_can_download_only_partner_scoped_booking_documents(): void
    {
        $this->seed();

        Storage::fake('local');

        $partnerUser = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $ownDocument = ManagedDocument::where('document_number', 'DOC-1002')->firstOrFail();
        $projectDocument = ManagedDocument::where('document_number', 'DOC-1001')->firstOrFail();

        Storage::disk('local')->put($ownDocument->storage_path, 'Partner booking document copy');
        Storage::disk('local')->put($projectDocument->storage_path, 'Internal project document');

        $this->actingAs($partnerUser)
            ->getJson(route('documents.index'))
            ->assertForbidden();

        $ownDownload = $this->actingAs($partnerUser)->get(route('documents.download', $ownDocument));

        $ownDownload
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertSame('Partner booking document copy', $ownDownload->streamedContent());

        $this->assertDatabaseHas('audit_events', [
            'user_id' => $partnerUser->id,
            'event_type' => 'documents.document.downloaded',
            'auditable_type' => ManagedDocument::class,
            'auditable_id' => $ownDocument->id,
        ]);

        $this->actingAs($partnerUser)
            ->get(route('documents.download', $projectDocument))
            ->assertForbidden();
    }

    public function test_partner_summary_rejects_unknown_filters(): void
    {
        $this->seed();

        $partnerUser = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $this->actingAs($partnerUser)
            ->getJson(route('partner.summary', ['limit' => 5, 'status' => 'open']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'The selected filter is not available for this endpoint.');
    }

    public function test_inactive_partner_records_do_not_expose_partner_portal_data(): void
    {
        $this->seed();

        $partnerUser = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        Partner::where('code', 'CP-001')->update(['status' => 'inactive']);

        $this->actingAs($partnerUser)
            ->getJson(route('partner.leads.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($partnerUser)
            ->getJson(route('partner.bookings.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($partnerUser)
            ->getJson(route('partner.summary'))
            ->assertOk()
            ->assertJsonPath('data.scope.partner_ids', [])
            ->assertJsonPath('data.metrics.leads', 0)
            ->assertJsonPath('data.metrics.bookings', 0)
            ->assertJsonPath('data.my_leads', [])
            ->assertJsonPath('data.site_visits', [])
            ->assertJsonPath('data.bookings', [])
            ->assertJsonPath('data.collections_follow_up', [])
            ->assertJsonPath('data.documents', []);

        $payload = app(Builder360Bootstrap::class)->forUser($partnerUser);

        $this->assertSame([], $payload['companies']);
        $this->assertSame([], $payload['projects']);
        $this->assertSame([], $payload['partner_pipeline']['partners']);
        $this->assertSame([], $payload['partner_pipeline']['lead_value_by_stage']);
        $this->assertSame([], $payload['partner_portal']['scope']['partner_ids']);
        $this->assertSame([], $payload['partner_portal']['my_leads']);
        $this->assertSame([], $payload['partner_portal']['site_visits']);
        $this->assertSame([], $payload['partner_portal']['bookings']);
        $this->assertSame(0, $payload['dashboard']['kpis']['leads']);
        $this->assertSame(0, $payload['dashboard']['kpis']['bookings']);
    }

    public function test_internal_wildcard_users_cannot_access_partner_portal_routes(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($director)
            ->getJson(route('partner.summary'))
            ->assertForbidden();

        $this->actingAs($director)
            ->getJson(route('partner.leads.index'))
            ->assertForbidden();

        $this->actingAs($director)
            ->getJson(route('partner.bookings.index'))
            ->assertForbidden();
    }

    public function test_partner_scope_requires_partner_role_not_only_matching_email_and_permission(): void
    {
        $this->seed();

        $partnerUser = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $misconfiguredInternalRole = Role::create([
            'slug' => 'misconfigured_internal_partner_permission',
            'name' => 'Misconfigured Internal Partner Permission',
            'scope_level' => 'department',
            'permissions' => ['partner.portal'],
            'is_active' => true,
        ]);

        $partnerUser->forceFill([
            'role_id' => $misconfiguredInternalRole->id,
        ])->save();
        $partnerUser->refresh()->load('role');

        $this->assertTrue($partnerUser->can('partner.portal'));
        $this->assertSame(
            [],
            app(PartnerScopeService::class)->activePartnerIdsForUser($partnerUser),
        );

        $payload = app(Builder360Bootstrap::class)->forUser($partnerUser);

        $this->assertNull($payload['partner_portal']);
        $this->assertSame([], collect($payload['partner_pipeline']['partners'])->pluck('code')->all());

        $this->actingAs($partnerUser)
            ->getJson(route('partner.summary'))
            ->assertForbidden();
    }
}
