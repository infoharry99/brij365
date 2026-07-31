<?php

namespace Tests\Feature;

use App\Application\Hr\Data\HrCommandData;
use ReflectionClass;
use Tests\TestCase;

class HrApplicationLayerTest extends TestCase
{
    public function test_shared_hr_command_data_is_immutable(): void
    {
        $this->assertTrue((new ReflectionClass(HrCommandData::class))->isReadOnly());
    }

    public function test_employee_controller_uses_focused_actions_without_queries_or_services(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Hr/EmployeeController.php'));
        foreach (['ListEmployeeWorkspace $workspace', 'ExportEmployeeRegister $export', 'ViewMyEmployeeProfilePage $view', 'ViewEmployeeProfilePage $view', 'CreateEmployee $create', 'UpdateEmployee $update'] as $boundary) {
            $this->assertStringContainsString($boundary, $controller);
        }
        $this->assertStringNotContainsString('::query()', $controller);
        $this->assertStringNotContainsString('EmployeeProfileService $', $controller);
        $this->assertStringNotContainsString('CompanyScopeService $', $controller);
    }

    public function test_attendance_controller_uses_focused_actions_without_queries_or_services(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Hr/AttendanceController.php'));
        foreach (['ListAttendanceWorkspace $workspace', 'ListAttendanceShifts $list', 'CreateAttendanceShift $create', 'ListAttendanceRecords $list', 'ListAttendanceRegularizations $list', 'SubmitAttendanceRegularization $submit', 'ApproveAttendanceRegularization $approve', 'RejectAttendanceRegularization $reject'] as $boundary) {
            $this->assertStringContainsString($boundary, $controller);
        }
        $this->assertStringNotContainsString('::query()', $controller);
        $this->assertStringNotContainsString('AttendanceService $', $controller);
        $this->assertStringNotContainsString('CompanyScopeService $', $controller);
    }

    public function test_leave_controllers_use_focused_actions_without_queries_or_services(): void
    {
        $leaveController = file_get_contents(app_path('Http/Controllers/Hr/LeaveController.php'));
        foreach (['ListLeaveWorkspace $workspace', 'ListLeaveTypes $list', 'ListLeaveBalances $list', 'ListLeaveRequests $list', 'SubmitLeaveRequest $submit', 'ApproveLeaveRequest $approve', 'RejectLeaveRequest $reject'] as $boundary) {
            $this->assertStringContainsString($boundary, $leaveController);
        }

        $processingController = file_get_contents(app_path('Http/Controllers/Hr/LeaveProcessingController.php'));
        foreach (['ListLeaveProcessingRuns $list', 'CreateLeaveProcessingRun $create', 'PostLeaveProcessingRun $post', 'ListLeaveEncashments $list', 'SubmitLeaveEncashment $submit', 'ApproveLeaveEncashment $approve', 'RejectLeaveEncashment $reject', 'MarkLeaveEncashmentForPayroll $mark'] as $boundary) {
            $this->assertStringContainsString($boundary, $processingController);
        }

        foreach ([$leaveController, $processingController] as $controller) {
            $this->assertStringNotContainsString('::query()', $controller);
            $this->assertStringNotContainsString('LeaveRequestService $', $controller);
            $this->assertStringNotContainsString('LeaveProcessingService $', $controller);
            $this->assertStringNotContainsString('CompanyScopeService $', $controller);
        }
    }

    public function test_performance_controller_uses_focused_actions_without_queries_or_services(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Hr/PerformanceController.php'));
        foreach (['ListPerformanceWorkspace $workspace', 'ListPerformanceCycles $list', 'CreatePerformanceCycle $create', 'ListPerformanceReviews $list', 'CreatePerformanceReview $create', 'SubmitSelfPerformanceReview $submit', 'SubmitManagerPerformanceReview $action', 'ClosePerformanceReview $close'] as $boundary) {
            $this->assertStringContainsString($boundary, $controller);
        }

        $this->assertStringNotContainsString('::query()', $controller);
        $this->assertStringNotContainsString('PerformanceManagementService $', $controller);
        $this->assertStringNotContainsString('CompanyScopeService $', $controller);
    }

    public function test_confirmation_controller_uses_focused_actions_without_queries_or_services(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Hr/EmployeeConfirmationController.php'));
        foreach (['ListConfirmationWorkspace $workspace', 'ListConfirmationCases $list', 'CreateConfirmationCase $create', 'SubmitConfirmationRecommendation $action', 'DecideConfirmationCase $decide'] as $boundary) {
            $this->assertStringContainsString($boundary, $controller);
        }

        $this->assertStringNotContainsString('::query()', $controller);
        $this->assertStringNotContainsString('EmployeeConfirmationService $', $controller);
        $this->assertStringNotContainsString('CompanyScopeService $', $controller);
    }

    public function test_separation_and_exit_controllers_use_focused_actions_without_queries_or_services(): void
    {
        $separation = file_get_contents(app_path('Http/Controllers/Hr/EmployeeSeparationSettlementController.php'));
        foreach (['ListSeparationWorkspace $workspace', 'ListSeparationSettlements $list', 'InitiateSeparationSettlement $initiate', 'ApproveSeparationSettlementByHr $approve', 'ApproveSeparationSettlementByFinance $approve', 'CompleteSeparationSettlement $complete'] as $boundary) {
            $this->assertStringContainsString($boundary, $separation);
        }

        $exit = file_get_contents(app_path('Http/Controllers/Hr/EmployeeExitInterviewController.php'));
        foreach (['ListExitInterviewWorkspace $workspace', 'ListExitInterviews $list', 'ViewExitInterviewSummary $summary', 'ScheduleExitInterview $schedule', 'SubmitExitInterview $action', 'ReviewExitInterview $review'] as $boundary) {
            $this->assertStringContainsString($boundary, $exit);
        }

        foreach ([$separation, $exit] as $controller) {
            $this->assertStringNotContainsString('::query()', $controller);
            $this->assertStringNotContainsString('CompanyScopeService $', $controller);
        }
        $this->assertStringNotContainsString('EmployeeSeparationSettlementService $', $separation);
        $this->assertStringNotContainsString('EmployeeExitInterviewService $', $exit);
    }

    public function test_employee_operations_controller_uses_focused_actions_without_queries_or_services(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Hr/EmployeeOperationsController.php'));
        foreach (['ListEmployeeOperationsWorkspace $workspace', 'ListEmployeeAssets $list', 'CreateEmployeeAsset $create', 'AssignEmployeeAsset $assign', 'RecoverEmployeeAsset $recover', 'ListExpenseClaims $list', 'SubmitExpenseClaim $submit', 'ApproveExpenseClaim $approve', 'RejectExpenseClaim $reject', 'PayExpenseClaim $pay', 'ListEmployeeLoans $list', 'SubmitEmployeeLoan $submit', 'ApproveEmployeeLoan $approve', 'RejectEmployeeLoan $reject', 'DisburseEmployeeLoan $disburse', 'ListHrHelpdeskTickets $list', 'CreateHrHelpdeskTicket $create', 'AssignHrHelpdeskTicket $assign', 'ResolveHrHelpdeskTicket $resolve', 'CloseHrHelpdeskTicket $close'] as $boundary) {
            $this->assertStringContainsString($boundary, $controller);
        }

        $this->assertStringNotContainsString('::query()', $controller);
        $this->assertStringNotContainsString('EmployeeOperationsService $', $controller);
        $this->assertStringNotContainsString('CompanyScopeService $', $controller);
    }

    public function test_documents_compliance_and_policy_controllers_use_focused_actions_without_queries_or_services(): void
    {
        $documents = file_get_contents(app_path('Http/Controllers/Hr/EmployeeDocumentController.php'));
        foreach (['ListEmployeeDocumentWorkspace $workspace', 'ListEmployeeDocumentRegister $list', 'ListEmployeeDocuments $list', 'SubmitEmployeeDocument $submit', 'ApproveEmployeeDocument $approve'] as $boundary) {
            $this->assertStringContainsString($boundary, $documents);
        }
        $compliance = file_get_contents(app_path('Http/Controllers/Hr/ComplianceRuleSettingController.php'));
        foreach (['ListComplianceRuleWorkspace $workspace', 'ListComplianceRules $list', 'CreateComplianceRuleDraft $create', 'ApproveComplianceRule $approve'] as $boundary) {
            $this->assertStringContainsString($boundary, $compliance);
        }
        $policies = file_get_contents(app_path('Http/Controllers/Hr/EmployeePolicyAcknowledgementController.php'));
        foreach (['ListPolicyAcknowledgementWorkspace $workspace', 'ListPolicyAcknowledgements $list', 'AcknowledgeEmployeePolicy $acknowledge'] as $boundary) {
            $this->assertStringContainsString($boundary, $policies);
        }

        foreach ([$documents, $compliance, $policies] as $controller) {
            $this->assertStringNotContainsString('::query()', $controller);
            $this->assertStringNotContainsString('CompanyScopeService $', $controller);
        }
        $this->assertStringNotContainsString('ManagedDocumentService $', $documents);
        $this->assertStringNotContainsString('SystemSettingService $', $compliance);
        $this->assertStringNotContainsString('EmployeePolicyAcknowledgementService $', $policies);
    }

    public function test_employee_supporting_controllers_use_focused_actions_without_queries_or_services(): void
    {
        $expectations = [
            'EmployeeProfileSectionController.php' => ['ViewEmployeeProfileSections $view', 'ViewEmployeeProfileSectionsPage $page', 'UpdateEmployeeProfileSections $update'],
            'EmployeeMovementController.php' => ['ListEmployeeMovementWorkspace $workspace', 'ListEmployeeMovements $list', 'CreateEmployeeMovement $create', 'ApproveEmployeeMovement $approve'],
            'EmployeePayrollSummaryController.php' => ['ViewEmployeePayrollSummary $view', 'ViewEmployeePayrollSummaryPage $page'],
            'EmployeeAuditEventController.php' => ['ListEmployeeAuditEvents $list', 'ListEmployeeAuditPage $page'],
        ];

        foreach ($expectations as $file => $boundaries) {
            $controller = file_get_contents(app_path('Http/Controllers/Hr/'.$file));
            foreach ($boundaries as $boundary) {
                $this->assertStringContainsString($boundary, $controller);
            }
            $this->assertStringNotContainsString('::query()', $controller);
        }
    }
}
