<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_receives_authoritative_approval_center_payload(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $response = $this->actingAs($director)
            ->getJson(route('builder360.approvals.index', ['tab' => 'pending']))
            ->assertOk()
            ->assertJsonStructure([
                'source',
                'generated_at',
                'scope' => ['company_id', 'project_id', 'role_slug'],
                'summary' => ['pending', 'high_priority', 'actionable', 'restricted', 'approved', 'value_tagged', 'total_value', 'modules'],
                'filters' => ['modules', 'priorities', 'statuses'],
                'rows',
                'pagination' => ['page', 'per_page', 'total', 'last_page'],
            ]);

        $this->assertSame('business-records', $response->json('source'));
        $this->assertIsArray($response->json('rows'));
        $this->assertIsArray($response->json('summary.modules'));
    }

    public function test_restricted_portal_roles_receive_no_internal_approval_payload(): void
    {
        $this->seed();

        foreach ([
            'rohan.shah@example.test',
            'sameer.bafna@partners.builder360.test',
            'farhan.shaikh@partners.builder360.test',
            'amit.verma@builder360.test',
        ] as $email) {
            $user = User::where('email', $email)->firstOrFail();

            $this->actingAs($user)
                ->getJson(route('builder360.approvals.index'))
                ->assertOk()
                ->assertJsonPath('restricted', true)
                ->assertJsonPath('summary.pending', 0)
                ->assertJsonCount(0, 'rows');
        }
    }

    public function test_filters_and_export_use_same_approval_contract(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($director)
            ->getJson(route('builder360.approvals.index', [
                'tab' => 'actionable',
                'priority' => 'high',
                'q' => 'approval',
            ]))
            ->assertOk()
            ->assertJsonStructure(['summary', 'filters', 'rows', 'pagination']);

        $this->actingAs($director)
            ->get(route('builder360.approvals.export', ['tab' => 'pending']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
