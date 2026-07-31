<?php

namespace Tests\Feature;

use App\Application\Hr\Data\LeaveProcessingLineItemData;
use App\Application\Hr\Data\LeaveProcessingRuleSnapshotData;
use App\Domain\Hr\Services\LeaveWorkspaceRegister;
use App\Models\Employee;
use App\Models\LeaveEncashment;
use App\Models\LeaveProcessingRun;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrLeaveWorkspacePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_leave_workspace_renders_only_the_selected_operational_surface(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $this->actingAs($hr)
            ->get(route('hr.leave-requests.index'))
            ->assertOk()
            ->assertSee('People Workspace')
            ->assertSee('Submit leave request')
            ->assertSee('Leave request register')
            ->assertDontSee('Generate processing preview')
            ->assertDontSee('Submit encashment');

        $this->actingAs($hr)
            ->get(route('hr.leave-processing-runs.index'))
            ->assertOk()
            ->assertSee('Leave processing run')
            ->assertSee('Processing safeguards')
            ->assertDontSee('Submit leave request')
            ->assertDontSee('Submit encashment');
    }

    public function test_policy_controls_expose_only_persisted_leave_type_rules(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();

        $this->actingAs($hr)
            ->get(route('hr.leave-types.index'))
            ->assertOk()
            ->assertSee('Leave policy controls')
            ->assertSee('No leave rule is hardcoded')
            ->assertSee('Annual entitlement')
            ->assertSee('Approval chain')
            ->assertDontSee('Sandwich leave');
    }

    public function test_leave_summary_is_scoped_to_the_actor_not_the_visible_page(): void
    {
        $this->seed();

        LeaveRequest::query()->firstOrFail()->update(['status' => 'submitted']);

        $employee = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $register = app(LeaveWorkspaceRegister::class);

        $this->assertSame(0, $register->summary($employee)->pendingRequests);
        $this->assertSame(1, $register->summary($hr)->pendingRequests);
    }

    public function test_row_actions_are_derived_from_each_record_policy(): void
    {
        $this->seed();

        $employeeUser = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $employee = Employee::where('user_id', $employeeUser->id)->firstOrFail();
        $leaveType = LeaveType::where('code', 'EL')->firstOrFail();
        $register = app(LeaveWorkspaceRegister::class);

        $requestId = $this->actingAs($employeeUser)->postJson(route('hr.leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'starts_on' => now()->addDays(70)->toDateString(),
            'ends_on' => now()->addDays(70)->toDateString(),
            'duration_unit' => 'full_day',
            'reason' => 'Verify policy-derived row actions.',
        ])->assertCreated()->json('data.id');

        $employeeRequestRow = $register->presentRequests($employeeUser, $register->requests($employeeUser))
            ->getCollection()->firstWhere('id', $requestId);
        $hrRequestRow = $register->presentRequests($hr, $register->requests($hr))
            ->getCollection()->firstWhere('id', $requestId);

        $this->assertFalse($employeeRequestRow->canApprove);
        $this->assertFalse($employeeRequestRow->canReject);
        $this->assertTrue($hrRequestRow->canApprove);
        $this->assertTrue($hrRequestRow->canReject);

        $runId = $this->actingAs($hr)->postJson(route('hr.leave-processing-runs.store'), [
            'period_year' => (int) now()->year,
            'processing_type' => 'monthly_accrual',
            'is_dry_run' => true,
            'note' => 'Verify creator separation of duties.',
        ])->assertCreated()->json('data.id');

        $creatorRunRow = $register->presentProcessingRuns($hr, $register->processingRuns($hr))
            ->getCollection()->firstWhere('id', $runId);
        $approverRunRow = $register->presentProcessingRuns($director, $register->processingRuns($director))
            ->getCollection()->firstWhere('id', $runId);

        $this->assertFalse($creatorRunRow->canPost);
        $this->assertTrue($approverRunRow->canPost);

        $encashmentId = $this->actingAs($employeeUser)->postJson(route('hr.leave-encashments.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'period_year' => (int) now()->year,
            'requested_days' => 1,
            'request_note' => 'Verify policy-derived encashment actions.',
        ])->assertCreated()->json('data.id');

        $requesterEncashmentRow = $register->presentEncashments($employeeUser, $register->encashments($employeeUser))
            ->getCollection()->firstWhere('id', $encashmentId);
        $hrEncashmentRow = $register->presentEncashments($hr, $register->encashments($hr))
            ->getCollection()->firstWhere('id', $encashmentId);

        $this->assertFalse($requesterEncashmentRow->canApprove);
        $this->assertFalse($requesterEncashmentRow->canReject);
        $this->assertFalse($requesterEncashmentRow->canMarkPayroll);
        $this->assertTrue($hrEncashmentRow->canApprove);
        $this->assertTrue($hrEncashmentRow->canReject);
        $this->assertFalse($hrEncashmentRow->canMarkPayroll);
        $this->assertInstanceOf(LeaveRequest::class, LeaveRequest::findOrFail($requestId));
        $this->assertInstanceOf(LeaveProcessingRun::class, LeaveProcessingRun::findOrFail($runId));
        $this->assertInstanceOf(LeaveEncashment::class, LeaveEncashment::findOrFail($encashmentId));
    }

    public function test_leave_presentation_sources_do_not_query_or_format_models_in_blade(): void
    {
        $service = file_get_contents(app_path('Domain/Hr/Services/LeaveWorkspaceRegister.php'));
        $views = collect(glob(resource_path('views/hr/leave/*.blade.php')))
            ->merge(glob(resource_path('views/hr/leave/partials/*.blade.php')))
            ->map(fn (string $path): string => file_get_contents($path))
            ->implode("\n");

        $this->assertIsString($service);
        $this->assertDoesNotMatchRegularExpression('/[^\x00-\x7F]/', $service);
        $this->assertStringNotContainsString('::query(', $views);
        $this->assertStringNotContainsString('->format(', $views);
        $this->assertStringNotContainsString('rules_snapshot', $views);
        $this->assertStringNotContainsString('line_items', $views);
        $this->assertStringContainsString("@include('hr.leave.partials.'.\$activeRegister)", $views);
        $this->assertStringContainsString("canReject: \$actor->can('reject', \$encashment)", $service);
    }

    public function test_processing_preview_exposes_only_persisted_snapshot_and_line_items_through_immutable_data(): void
    {
        $this->seed();

        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $register = app(LeaveWorkspaceRegister::class);

        $runId = $this->actingAs($hr)->postJson(route('hr.leave-processing-runs.store'), [
            'period_year' => (int) now()->year,
            'processing_type' => 'monthly_accrual',
            'is_dry_run' => true,
            'note' => 'Verify persisted preview presentation.',
        ])->assertCreated()->json('data.id');

        $row = $register->presentProcessingRuns($hr, $register->processingRuns($hr))
            ->getCollection()
            ->firstWhere('id', $runId);

        $this->assertInstanceOf(LeaveProcessingRuleSnapshotData::class, $row->rulesSnapshot);
        $this->assertNotEmpty($row->rulesSnapshot->leaveTypes);
        $this->assertSame('hr.leave.rules', $row->rulesSnapshot->settingKey);
        $this->assertNotEmpty($row->lineItems);
        $this->assertInstanceOf(LeaveProcessingLineItemData::class, $row->lineItems[0]);
        $this->assertNotSame('', $row->lineItems[0]->employeeName);

        $run = LeaveProcessingRun::findOrFail($runId);
        $snapshotEntitlement = $row->rulesSnapshot->leaveTypes[0]->annualEntitlementDays;
        LeaveType::where('code', $row->rulesSnapshot->leaveTypes[0]->code)
            ->firstOrFail()
            ->update(['annual_entitlement_days' => 99]);
        $persistedRow = $register->presentProcessingRuns($hr, $register->processingRuns($hr))
            ->getCollection()
            ->firstWhere('id', $run->id);

        $this->assertSame($snapshotEntitlement, $persistedRow->rulesSnapshot->leaveTypes[0]->annualEntitlementDays);

        $this->actingAs($hr)
            ->get(route('hr.leave-processing-runs.index'))
            ->assertOk()
            ->assertSee('Persisted preview details')
            ->assertSee('Rules captured for this run')
            ->assertSee('Persisted employee processing lines')
            ->assertSee($row->lineItems[0]->employeeName);
    }

    public function test_encashment_rejection_uses_the_dedicated_reject_policy_ability(): void
    {
        $this->seed();

        $register = file_get_contents(app_path('Domain/Hr/Services/LeaveWorkspaceRegister.php'));
        $request = file_get_contents(app_path('Http/Requests/Hr/RejectLeaveEncashmentRequest.php'));
        $policy = file_get_contents(app_path('Policies/LeaveEncashmentPolicy.php'));

        $this->assertIsString($register);
        $this->assertIsString($request);
        $this->assertIsString($policy);
        $this->assertStringContainsString("canReject: \$actor->can('reject', \$encashment)", $register);
        $this->assertStringContainsString("can('reject', \$encashment)", $request);
        $this->assertStringContainsString('function reject(User $user, LeaveEncashment $encashment)', $policy);

        $employeeUser = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $employee = Employee::where('user_id', $employeeUser->id)->firstOrFail();
        $leaveType = LeaveType::where('code', 'EL')->firstOrFail();

        $encashmentId = $this->actingAs($employeeUser)->postJson(route('hr.leave-encashments.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'period_year' => (int) now()->year,
            'requested_days' => 1,
            'request_note' => 'Verify the dedicated rejection authorization path.',
        ])->assertCreated()->json('data.id');

        $this->actingAs($hr)
            ->patchJson(route('hr.leave-encashments.reject', $encashmentId), [
                'decision_note' => 'Rejected through the explicit reject ability.',
            ])
            ->assertOk();

        $this->assertSame('rejected', LeaveEncashment::findOrFail($encashmentId)->status);
    }
}
