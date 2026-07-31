<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeAsset;
use App\Models\HrHelpdeskTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrAssetsAndHelpdeskWorkspacePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_workspace_is_focused_responsive_and_uses_full_register_summary(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $total = EmployeeAsset::where('company_id', $hr->company_id)->count();

        $this->actingAs($hr)
            ->get(route('hr.assets.index', ['status' => 'assigned']))
            ->assertOk()
            ->assertSee('Register employee asset')
            ->assertSee('Employee asset register')
            ->assertSee('<span>Total assets</span><strong>'.$total.'</strong>', false)
            ->assertSee('people-ops-table-wrap', false)
            ->assertSee('people-ops-mobile-list', false)
            ->assertSee('aria-current="page"', false)
            ->assertDontSee('<form method="POST" action="'.route('hr.expense-claims.store').'"', false);
    }

    public function test_asset_mobile_and_desktop_presentations_keep_policy_authorized_actions(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $asset = EmployeeAsset::where('status', 'assigned')->whereNotNull('employee_id')->firstOrFail();

        $this->actingAs($hr)
            ->get(route('hr.assets.index'))
            ->assertOk()
            ->assertSee(route('hr.assets.recover', $asset), false)
            ->assertSee('Record recovery');

        $this->actingAs($employee)
            ->get(route('hr.assets.index'))
            ->assertOk()
            ->assertDontSee(route('hr.assets.recover', $asset), false)
            ->assertDontSee('Record recovery');
    }

    public function test_helpdesk_workspace_exposes_governed_workflow_without_leaking_attachment_urls(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('company_id', $hr->company_id)->whereNotNull('user_id')->firstOrFail();
        $inactiveAssignee = User::where('company_id', $hr->company_id)->where('id', '!=', $hr->id)->firstOrFail();
        $inactiveAssignee->forceFill(['status' => 'inactive'])->save();

        $ticket = HrHelpdeskTicket::create([
            'company_id' => $hr->company_id,
            'employee_id' => $employee->id,
            'raised_by_user_id' => $employee->user_id,
            'ticket_number' => 'HRT-PRESENT-001',
            'category' => 'documents',
            'priority' => 'critical',
            'status' => 'open',
            'subject' => 'Employment letter support request',
            'description' => 'Please provide the governed employment letter for verification.',
            'attachments' => [[
                'name' => 'employment-request.pdf',
                'url' => 'https://private.example.test/never-render-this-token',
            ]],
            'workflow_history' => [],
        ]);

        $response = $this->actingAs($hr)->get(route('hr.helpdesk-tickets.index'));

        $response
            ->assertOk()
            ->assertSee('Raise HR helpdesk ticket')
            ->assertSee('HR helpdesk ticket register')
            ->assertSee('Employment letter support request')
            ->assertSee('employment-request.pdf')
            ->assertDontSee('never-render-this-token')
            ->assertDontSee($inactiveAssignee->email)
            ->assertSee(route('hr.helpdesk-tickets.assign', $ticket), false)
            ->assertSee(route('hr.helpdesk-tickets.resolve', $ticket), false)
            ->assertSee('people-ops-mobile-list', false);
    }

    public function test_self_service_helpdesk_page_is_limited_to_the_authenticated_employee(): void
    {
        $this->seed();

        $employeeUser = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $ownEmployee = Employee::where('user_id', $employeeUser->id)->firstOrFail();
        $otherEmployee = Employee::where('company_id', $ownEmployee->company_id)
            ->where('id', '!=', $ownEmployee->id)
            ->whereNotNull('user_id')
            ->firstOrFail();

        HrHelpdeskTicket::create([
            'company_id' => $ownEmployee->company_id,
            'employee_id' => $otherEmployee->id,
            'raised_by_user_id' => $otherEmployee->user_id,
            'ticket_number' => 'HRT-HIDDEN-001',
            'category' => 'policy',
            'priority' => 'medium',
            'status' => 'open',
            'subject' => 'Another employee private ticket',
            'description' => 'This helpdesk request must not appear for another employee.',
            'workflow_history' => [],
        ]);

        $this->actingAs($employeeUser)
            ->get(route('hr.helpdesk-tickets.index'))
            ->assertOk()
            ->assertDontSee('Another employee private ticket')
            ->assertSee('Raise HR helpdesk ticket')
            ->assertDontSee('Assign ticket')
            ->assertDontSee('Resolve ticket');
    }

    public function test_asset_and_helpdesk_presentation_contracts_use_immutable_dtos_and_focused_partials(): void
    {
        $root = dirname(__DIR__, 2);

        $assetDto = file_get_contents($root.'/app/Application/Hr/Data/EmployeeAssetRowData.php');
        $helpdeskDto = file_get_contents($root.'/app/Application/Hr/Data/HrHelpdeskTicketRowData.php');
        $workspace = file_get_contents($root.'/resources/views/hr/operations/workspace.blade.php');
        $assetView = file_get_contents($root.'/resources/views/hr/operations/partials/assets.blade.php');
        $helpdeskView = file_get_contents($root.'/resources/views/hr/operations/partials/helpdesk.blade.php');

        $this->assertStringContainsString('final readonly class EmployeeAssetRowData', $assetDto);
        $this->assertStringContainsString('final readonly class HrHelpdeskTicketRowData', $helpdeskDto);
        $this->assertStringContainsString("@include('hr.operations.partials.people-workspace')", $workspace);
        $this->assertStringContainsString('<caption>Employee asset register</caption>', $assetView);
        $this->assertStringContainsString('people-ops-mobile-list', $assetView);
        $this->assertStringContainsString('<caption>HR helpdesk ticket register</caption>', $helpdeskView);
        $this->assertStringContainsString('people-ops-mobile-list', $helpdeskView);
        $this->assertStringNotContainsString('blade-workspace', $workspace);
    }
}
