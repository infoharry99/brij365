<?php

use App\Http\Controllers\Admin\CompanyAdministrationController;
use App\Http\Controllers\Admin\RoleAdministrationController;
use App\Http\Controllers\Admin\UserAdministrationController;
use App\Http\Controllers\AfterSales\AfterSalesController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Builder360\ApprovalCenterController;
use App\Http\Controllers\Builder360\ClassicDashboardController;
use App\Http\Controllers\Builder360\DashboardController;
use App\Http\Controllers\Builder360\GlobalSearchController;
use App\Http\Controllers\Builder360\LegacyDashboardController;
use App\Http\Controllers\Builder360\ProfileController;
use App\Http\Controllers\Builder360\ProfilePhotoController;
use App\Http\Controllers\Builder360\RoleContextController;
use App\Http\Controllers\Builder360\ThemePreferenceController;
use App\Http\Controllers\Buyer\BuyerPortalController;
use App\Http\Controllers\Collaboration\CollaborationController;
use App\Http\Controllers\Collaboration\WorkTaskLifecycleController;
use App\Http\Controllers\Construction\ConstructionController;
use App\Http\Controllers\Crm\LeadController;
use App\Http\Controllers\Crm\LeadEngagementController;
use App\Http\Controllers\Crm\MarketingCampaignController;
use App\Http\Controllers\Crm\ProspectInquiryController;
use App\Http\Controllers\Crm\SalesAnalyticsController;
use App\Http\Controllers\Documents\DocumentCategoryController;
use App\Http\Controllers\Documents\ManagedDocumentController;
use App\Http\Controllers\Finance\CollectionReceiptController;
use App\Http\Controllers\Finance\FinanceDashboardController;
use App\Http\Controllers\Finance\FinancialVoucherController;
use App\Http\Controllers\Finance\GstComplianceController;
use App\Http\Controllers\Finance\PaymentGatewayWebhookController;
use App\Http\Controllers\Finance\PaymentRequestController;
use App\Http\Controllers\Governance\AuditTrailController;
use App\Http\Controllers\Governance\ManagementReportController;
use App\Http\Controllers\Hr\AttendanceController;
use App\Http\Controllers\Hr\AttendanceRosterController;
use App\Http\Controllers\Hr\ComplianceRuleSettingController;
use App\Http\Controllers\Hr\EmployeeAuditEventController;
use App\Http\Controllers\Hr\EmployeeConfirmationController;
use App\Http\Controllers\Hr\EmployeeController;
use App\Http\Controllers\Hr\HrDashboardController;
use App\Http\Controllers\Hr\HrReportController;
use App\Http\Controllers\Hr\HrSettingController;
use App\Http\Controllers\Hr\EmployeeDocumentController;
use App\Http\Controllers\Hr\EmployeeExitInterviewController;
use App\Http\Controllers\Hr\EmployeeLifecycleController;
use App\Http\Controllers\Hr\EmployeeMovementController;
use App\Http\Controllers\Hr\EmployeeOperationsController;
use App\Http\Controllers\Hr\EmployeePayrollSummaryController;
use App\Http\Controllers\Hr\EmployeePolicyAcknowledgementController;
use App\Http\Controllers\Hr\EmployeeProfileSectionController;
use App\Http\Controllers\Hr\EmployeeSeparationSettlementController;
use App\Http\Controllers\Hr\LeaveController;
use App\Http\Controllers\Hr\LeaveProcessingController;
use App\Http\Controllers\Hr\PerformanceController;
use App\Http\Controllers\Inventory\ProjectUnitController;
use App\Http\Controllers\Inventory\UnitPricingController;
use App\Http\Controllers\Legal\LegalComplianceController;
use App\Http\Controllers\Maintenance\MaintenanceSocietyController;
use App\Http\Controllers\Mailbox\MailboxAccountController;
use App\Http\Controllers\Notifications\NotificationCenterController;
use App\Http\Controllers\Operations\HealthCheckController;
use App\Http\Controllers\Partner\PartnerBookingController;
use App\Http\Controllers\Partner\PartnerDashboardController;
use App\Http\Controllers\Partner\PartnerLeadController;
use App\Http\Controllers\Payroll\CommissionController;
use App\Http\Controllers\Payroll\PayrollController;
use App\Http\Controllers\Payroll\TaxDocumentController;
use App\Http\Controllers\Payroll\EmployeeTaxProfileController;
use App\Http\Controllers\Possession\PossessionHandoverController;
use App\Http\Controllers\Procurement\ProcurementController;
use App\Http\Controllers\Procurement\VendorPerformanceScoreController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Projects\ProjectHealthScoreController;
use App\Http\Controllers\Recruitment\RecruitmentController;
use App\Http\Controllers\Sales\BookingController;
use App\Http\Controllers\Sales\BookingQuoteController;
use App\Http\Controllers\Scoring\ScoringOverviewController;
use App\Http\Controllers\Scoring\AttendanceRosterRulePackController;
use App\Http\Controllers\Scoring\PerformanceScoringSimulationController;
use App\Http\Controllers\Scoring\RosterImpactSimulationController;
use App\Http\Controllers\Scoring\ScoringRuleController;
use App\Http\Controllers\Scoring\ScoringSnapshotController;
use App\Http\Controllers\Settings\DataImportController;
use App\Http\Controllers\Settings\SystemSettingController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthCheckController::class, 'health'])->name('health');

Route::post('/prospect-inquiries', [ProspectInquiryController::class, 'storePublic'])
    ->middleware('throttle:30,1')
    ->name('prospect-inquiries.store');

Route::post('/finance/payment-gateway/webhook', PaymentGatewayWebhookController::class)
    ->middleware('throttle:60,1')
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('finance.payment-gateway.webhook');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware(['auth', 'account.active'])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/verify-email', EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::middleware(['auth', 'account.active', 'verified', 'company.active', 'throttle:erp-read', 'erp.write_limit'])->group(function (): void {
    Route::get('/', DashboardController::class)->name('builder360.dashboard');
    Route::get('/profile', ProfileController::class)->name('builder360.profile');
    Route::patch('/profile/photo', [ProfilePhotoController::class, 'update'])->name('builder360.profile-photo.update');
    Route::get('/users/{user}/profile-photo', [ProfilePhotoController::class, 'show'])->name('builder360.profile-photo.show');
    Route::get('/search', GlobalSearchController::class)->name('builder360.search');
    Route::post('/theme', ThemePreferenceController::class)->name('builder360.theme.store');
    Route::get('/scoring', ScoringOverviewController::class)->name('scoring.index');
    Route::post('/scoring/attendance-roster-rule-packs', AttendanceRosterRulePackController::class)
        ->name('scoring.attendance-roster-rule-packs.store');
    Route::post('/scoring/rules/{scoringRule}/simulate-performance', PerformanceScoringSimulationController::class)
        ->name('scoring.performance-simulations.store');
    Route::post('/scoring/attendance-rotation-rules/{attendanceRotationRule}/simulate-roster', RosterImpactSimulationController::class)
        ->name('scoring.roster-simulations.store');
    Route::post('/scoring/rules', [ScoringRuleController::class, 'store'])->name('scoring.rules.store');
    Route::get('/scoring/rules/{scoringRule}', [ScoringRuleController::class, 'show'])->name('scoring.rules.show');
    Route::get('/scoring/rules/{scoringRule}/export', [ScoringRuleController::class, 'export'])->name('scoring.rules.export');
    Route::get('/scoring/rules/{scoringRule}/edit', [ScoringRuleController::class, 'edit'])->name('scoring.rules.edit');
    Route::patch('/scoring/rules/{scoringRule}', [ScoringRuleController::class, 'update'])->name('scoring.rules.update');
    Route::patch('/scoring/rules/{scoringRule}/validate', [ScoringRuleController::class, 'validateRule'])->name('scoring.rules.validate');
    Route::patch('/scoring/rules/{scoringRule}/submit', [ScoringRuleController::class, 'submit'])->name('scoring.rules.submit');
    Route::patch('/scoring/rules/{scoringRule}/approve', [ScoringRuleController::class, 'approve'])->name('scoring.rules.approve');
    Route::patch('/scoring/rules/{scoringRule}/activate', [ScoringRuleController::class, 'activate'])->name('scoring.rules.activate');
    Route::post('/scoring/rules/{scoringRule}/clone', [ScoringRuleController::class, 'clone'])->name('scoring.rules.clone');
    Route::post('/scoring/rules/{scoringRule}/rollback', [ScoringRuleController::class, 'rollback'])->name('scoring.rules.rollback');
    Route::post('/scoring/rules/{scoringRule}/recalculate', [ScoringRuleController::class, 'recalculate'])->name('scoring.rules.recalculate');
    Route::patch('/scoring/rules/{scoringRule}/reject', [ScoringRuleController::class, 'reject'])->name('scoring.rules.reject');
    Route::patch('/scoring/rules/{scoringRule}/retire', [ScoringRuleController::class, 'retire'])->name('scoring.rules.retire');
    Route::post('/scoring/snapshots/{scoreSnapshot}/override', [ScoringSnapshotController::class, 'override'])->name('scoring.snapshots.override');
    Route::get('/classic/dashboard', ClassicDashboardController::class)->name('builder360.classic.dashboard');
    Route::get('/builder360/app', LegacyDashboardController::class)->name('builder360.legacy-app');
    Route::get('/builder360/bootstrap', [RoleContextController::class, 'show'])->name('builder360.bootstrap');
    Route::post('/builder360/role-context', [RoleContextController::class, 'store'])->name('builder360.role-context.store');
    Route::post('/builder360/project-context', [RoleContextController::class, 'storeProject'])->name('builder360.project-context.store');
    Route::post('/builder360/dashboard-context', [RoleContextController::class, 'storeDashboard'])->name('builder360.dashboard-context.store');
    Route::get('/builder360/approvals', [ApprovalCenterController::class, 'index'])->name('builder360.approvals.index');
    Route::get('/builder360/approvals/export', [ApprovalCenterController::class, 'export'])->name('builder360.approvals.export');

    Route::prefix('operations')->name('operations.')->group(function (): void {
        Route::get('/readiness', [HealthCheckController::class, 'readiness'])->name('readiness');
    });

    Route::prefix('crm')->name('crm.')->group(function (): void {
        Route::get('/prospect-inquiries', [ProspectInquiryController::class, 'index'])->name('prospect-inquiries.index');
        Route::patch('/prospect-inquiries/{prospectInquiry}/assign', [ProspectInquiryController::class, 'assign'])->name('prospect-inquiries.assign');
        Route::patch('/prospect-inquiries/{prospectInquiry}/convert', [ProspectInquiryController::class, 'convert'])->name('prospect-inquiries.convert');
        Route::patch('/prospect-inquiries/{prospectInquiry}/close', [ProspectInquiryController::class, 'close'])->name('prospect-inquiries.close');
        Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
        Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
        Route::patch('/leads/{lead}/stage', [LeadController::class, 'updateStage'])->name('leads.stage.update');
        Route::patch('/leads/{lead}/disposition', [LeadController::class, 'dispose'])->name('leads.disposition.update');
        Route::get('/campaigns', [MarketingCampaignController::class, 'index'])->name('campaigns.index');
        Route::post('/campaigns', [MarketingCampaignController::class, 'store'])->name('campaigns.store');
        Route::patch('/campaigns/{marketingCampaign}/status', [MarketingCampaignController::class, 'updateStatus'])->name('campaigns.status.update');
        Route::get('/lead-activities', [MarketingCampaignController::class, 'activities'])->name('lead-activities.index');
        Route::post('/lead-activities', [MarketingCampaignController::class, 'storeActivity'])->name('lead-activities.store');
        Route::get('/analytics', SalesAnalyticsController::class)->name('analytics.index');
        Route::get('/lead-qualifications', [LeadEngagementController::class, 'qualifications'])->name('lead-qualifications.index');
        Route::post('/lead-qualifications', [LeadEngagementController::class, 'storeQualification'])->name('lead-qualifications.store');
        Route::get('/site-visits', [LeadEngagementController::class, 'siteVisits'])->name('site-visits.index');
        Route::post('/site-visits', [LeadEngagementController::class, 'storeSiteVisit'])->name('site-visits.store');
        Route::patch('/site-visits/{siteVisit}', [LeadEngagementController::class, 'updateSiteVisit'])->name('site-visits.update');
        Route::patch('/site-visits/{siteVisit}/complete', [LeadEngagementController::class, 'completeSiteVisit'])->name('site-visits.complete');
        Route::patch('/site-visits/{siteVisit}/cancel', [LeadEngagementController::class, 'cancelSiteVisit'])->name('site-visits.cancel');
    });

    Route::prefix('inventory')->name('inventory.')->group(function (): void {
        Route::get('/units', [ProjectUnitController::class, 'index'])->name('units.index');
        Route::get('/units/export', [ProjectUnitController::class, 'export'])->name('units.export');
        Route::get('/unit-price-versions', [UnitPricingController::class, 'index'])->name('unit-price-versions.index');
        Route::post('/unit-price-versions', [UnitPricingController::class, 'store'])->name('unit-price-versions.store');
        Route::patch('/unit-price-versions/{unitPriceVersion}/approve', [UnitPricingController::class, 'approve'])->name('unit-price-versions.approve');
    });

    Route::prefix('projects')->name('projects.')->group(function (): void {
        Route::get('/', [ProjectController::class, 'index'])->name('index');
        Route::get('/cost-roi/export', [ProjectController::class, 'exportCostRoi'])->name('cost-roi.export');
        Route::post('/', [ProjectController::class, 'store'])->name('store');
        Route::post('/{project}/team-assignments', [ProjectController::class, 'storeTeamAssignment'])->name('team-assignments.store');
        Route::delete('/{project}/team-assignments/{projectTeamAssignment}', [ProjectController::class, 'destroyTeamAssignment'])->name('team-assignments.destroy');
        Route::patch('/{project}/health-score', [ProjectHealthScoreController::class, 'update'])->name('health-score.update');
        Route::patch('/{project}', [ProjectController::class, 'update'])->name('update');
    });

    Route::prefix('sales')->name('sales.')->group(function (): void {
        Route::post('/booking-quotes', BookingQuoteController::class)->name('booking-quotes.store');
        Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
        Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    });

    Route::prefix('finance')->name('finance.')->group(function (): void {
        Route::get('/dashboard', FinanceDashboardController::class)->name('dashboard');
        Route::get('/collections', [CollectionReceiptController::class, 'index'])->name('collections.index');
        Route::get('/collections/export', [CollectionReceiptController::class, 'export'])->name('collections.export');
        Route::post('/collections', [CollectionReceiptController::class, 'store'])->name('collections.store');
        Route::patch('/collections/{collectionReceipt}/approve', [CollectionReceiptController::class, 'approve'])->name('collections.approve');
        Route::get('/payment-requests', [PaymentRequestController::class, 'index'])->name('payment-requests.index');
        Route::post('/payment-requests', [PaymentRequestController::class, 'store'])->name('payment-requests.store');
        Route::patch('/payment-requests/{paymentRequest}/cancel', [PaymentRequestController::class, 'cancel'])->name('payment-requests.cancel');
        Route::get('/vouchers', [FinancialVoucherController::class, 'index'])->name('vouchers.index');
        Route::post('/vouchers', [FinancialVoucherController::class, 'store'])->name('vouchers.store');
        Route::patch('/vouchers/{financialVoucher}/approve', [FinancialVoucherController::class, 'approve'])->name('vouchers.approve');
        Route::patch('/vouchers/{financialVoucher}/reject', [FinancialVoucherController::class, 'reject'])->name('vouchers.reject');
        Route::get('/gst-entries', [GstComplianceController::class, 'entries'])->name('gst-entries.index');
        Route::post('/gst-entries', [GstComplianceController::class, 'storeEntry'])->name('gst-entries.store');
        Route::patch('/gst-entries/{gstEntry}/approve', [GstComplianceController::class, 'approveEntry'])->name('gst-entries.approve');
        Route::get('/gst-return-periods', [GstComplianceController::class, 'periods'])->name('gst-return-periods.index');
        Route::post('/gst-return-periods', [GstComplianceController::class, 'preparePeriod'])->name('gst-return-periods.store');
        Route::patch('/gst-return-periods/{gstReturnPeriod}/approve', [GstComplianceController::class, 'approvePeriod'])->name('gst-return-periods.approve');
        Route::patch('/gst-return-periods/{gstReturnPeriod}/lock', [GstComplianceController::class, 'lockPeriod'])->name('gst-return-periods.lock');
    });

    Route::prefix('documents')->name('documents.')->group(function (): void {
        Route::get('/categories', [DocumentCategoryController::class, 'index'])->name('categories.index');
        Route::get('/', [ManagedDocumentController::class, 'index'])->name('index');
        Route::post('/', [ManagedDocumentController::class, 'store'])->name('store');
        Route::get('/{managedDocument}/download', [ManagedDocumentController::class, 'download'])->name('download');
        Route::patch('/{managedDocument}/approve', [ManagedDocumentController::class, 'approve'])->name('approve');
    });

    Route::prefix('hr')->name('hr.')->group(function (): void {
        Route::get('/dashboard', HrDashboardController::class)->name('dashboard');
        Route::get('/reports', HrReportController::class)->name('reports.index');
        Route::get('/settings', HrSettingController::class)->name('settings.index');
        Route::get('/employees/me', [EmployeeController::class, 'me'])->name('employees.me');
        Route::get('/employees/me/profile', [EmployeeController::class, 'myProfile'])->name('employees.me.profile');
        Route::get('/employees/me/tax-inputs', [EmployeeTaxProfileController::class, 'editMine'])->name('employees.me.tax-inputs.edit');
        Route::put('/employees/me/tax-inputs', [EmployeeTaxProfileController::class, 'saveMine'])->name('employees.me.tax-inputs.update');
        Route::patch('/employees/me/tax-inputs/{employeeTaxProfile}/submit', [EmployeeTaxProfileController::class, 'submitMine'])->name('employees.me.tax-inputs.submit');
        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('/employees/export', [EmployeeController::class, 'export'])->name('employees.export');
        Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/employee-documents', [EmployeeDocumentController::class, 'register'])->name('employee-documents.index');
        Route::get('/employees/{employee}/movements', [EmployeeMovementController::class, 'index'])->name('employees.movements.index');
        Route::post('/employees/{employee}/movements', [EmployeeMovementController::class, 'store'])->name('employees.movements.store');
        Route::patch('/employees/{employee}/movements/{employeeMovement}/approve', [EmployeeMovementController::class, 'approve'])->name('employees.movements.approve');
        Route::get('/employees/{employee}/documents', [EmployeeDocumentController::class, 'index'])->name('employees.documents.index');
        Route::post('/employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])->name('employees.documents.store');
        Route::patch('/employees/{employee}/documents/{managedDocument}/approve', [EmployeeDocumentController::class, 'approve'])->name('employees.documents.approve');
        Route::get('/employees/{employee}/profile-sections', [EmployeeProfileSectionController::class, 'show'])->name('employees.profile-sections.show');
        Route::patch('/employees/{employee}/profile-sections', [EmployeeProfileSectionController::class, 'update'])->name('employees.profile-sections.update');
        Route::get('/employees/{employee}/payroll-summary', [EmployeePayrollSummaryController::class, 'show'])->name('employees.payroll-summary.show');
        Route::get('/employees/{employee}/audit-events', [EmployeeAuditEventController::class, 'index'])->name('employees.audit-events.index');
        Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
        Route::patch('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::get('/assets', [EmployeeOperationsController::class, 'assets'])->name('assets.index');
        Route::post('/assets', [EmployeeOperationsController::class, 'storeAsset'])->name('assets.store');
        Route::patch('/assets/{employeeAsset}/assign', [EmployeeOperationsController::class, 'assignAsset'])->name('assets.assign');
        Route::patch('/assets/{employeeAsset}/recover', [EmployeeOperationsController::class, 'recoverAsset'])->name('assets.recover');
        Route::get('/expense-claims', [EmployeeOperationsController::class, 'claims'])->name('expense-claims.index');
        Route::post('/expense-claims', [EmployeeOperationsController::class, 'storeClaim'])->name('expense-claims.store');
        Route::patch('/expense-claims/{expenseClaim}/approve', [EmployeeOperationsController::class, 'approveClaim'])->name('expense-claims.approve');
        Route::patch('/expense-claims/{expenseClaim}/reject', [EmployeeOperationsController::class, 'rejectClaim'])->name('expense-claims.reject');
        Route::patch('/expense-claims/{expenseClaim}/pay', [EmployeeOperationsController::class, 'payClaim'])->name('expense-claims.pay');
        Route::get('/loans', [EmployeeOperationsController::class, 'loans'])->name('loans.index');
        Route::post('/loans', [EmployeeOperationsController::class, 'storeLoan'])->name('loans.store');
        Route::patch('/loans/{employeeLoan}/approve', [EmployeeOperationsController::class, 'approveLoan'])->name('loans.approve');
        Route::patch('/loans/{employeeLoan}/reject', [EmployeeOperationsController::class, 'rejectLoan'])->name('loans.reject');
        Route::patch('/loans/{employeeLoan}/disburse', [EmployeeOperationsController::class, 'disburseLoan'])->name('loans.disburse');
        Route::get('/helpdesk-tickets', [EmployeeOperationsController::class, 'helpdeskTickets'])->name('helpdesk-tickets.index');
        Route::post('/helpdesk-tickets', [EmployeeOperationsController::class, 'storeHelpdeskTicket'])->name('helpdesk-tickets.store');
        Route::patch('/helpdesk-tickets/{hrHelpdeskTicket}/assign', [EmployeeOperationsController::class, 'assignHelpdeskTicket'])->name('helpdesk-tickets.assign');
        Route::patch('/helpdesk-tickets/{hrHelpdeskTicket}/resolve', [EmployeeOperationsController::class, 'resolveHelpdeskTicket'])->name('helpdesk-tickets.resolve');
        Route::patch('/helpdesk-tickets/{hrHelpdeskTicket}/close', [EmployeeOperationsController::class, 'closeHelpdeskTicket'])->name('helpdesk-tickets.close');
        Route::get('/policy-acknowledgements', [EmployeePolicyAcknowledgementController::class, 'index'])->name('policy-acknowledgements.index');
        Route::post('/policy-acknowledgements', [EmployeePolicyAcknowledgementController::class, 'store'])->name('policy-acknowledgements.store');
        Route::get('/compliance-rule-settings', [ComplianceRuleSettingController::class, 'index'])->name('compliance-rule-settings.index');
        Route::post('/compliance-rule-settings', [ComplianceRuleSettingController::class, 'store'])->name('compliance-rule-settings.store');
        Route::post('/compliance-rule-settings/{systemSetting}/simulate', [ComplianceRuleSettingController::class, 'simulate'])->middleware('throttle:30,1')->name('compliance-rule-settings.simulate');
        Route::patch('/compliance-rule-settings/{systemSetting}/verify', [ComplianceRuleSettingController::class, 'verify'])->name('compliance-rule-settings.verify');
        Route::patch('/compliance-rule-settings/{systemSetting}/approve', [ComplianceRuleSettingController::class, 'approve'])->name('compliance-rule-settings.approve');
        Route::get('/leave-types', [LeaveController::class, 'types'])->name('leave-types.index');
        Route::get('/leave-balances', [LeaveController::class, 'balances'])->name('leave-balances.index');
        Route::get('/leave-requests', [LeaveController::class, 'index'])->name('leave-requests.index');
        Route::post('/leave-requests', [LeaveController::class, 'store'])->name('leave-requests.store');
        Route::patch('/leave-requests/{leaveRequest}/approve', [LeaveController::class, 'approve'])->name('leave-requests.approve');
        Route::patch('/leave-requests/{leaveRequest}/reject', [LeaveController::class, 'reject'])->name('leave-requests.reject');
        Route::get('/leave-processing-runs', [LeaveProcessingController::class, 'processingRuns'])->name('leave-processing-runs.index');
        Route::post('/leave-processing-runs', [LeaveProcessingController::class, 'storeProcessingRun'])->name('leave-processing-runs.store');
        Route::patch('/leave-processing-runs/{leaveProcessingRun}/post', [LeaveProcessingController::class, 'postProcessingRun'])->name('leave-processing-runs.post');
        Route::get('/leave-encashments', [LeaveProcessingController::class, 'encashments'])->name('leave-encashments.index');
        Route::post('/leave-encashments', [LeaveProcessingController::class, 'storeEncashment'])->name('leave-encashments.store');
        Route::patch('/leave-encashments/{leaveEncashment}/approve', [LeaveProcessingController::class, 'approveEncashment'])->name('leave-encashments.approve');
        Route::patch('/leave-encashments/{leaveEncashment}/reject', [LeaveProcessingController::class, 'rejectEncashment'])->name('leave-encashments.reject');
        Route::patch('/leave-encashments/{leaveEncashment}/mark-payroll', [LeaveProcessingController::class, 'markEncashmentPayroll'])->name('leave-encashments.mark-payroll');
        Route::get('/attendance-shifts', [AttendanceController::class, 'shifts'])->name('attendance-shifts.index');
        Route::post('/attendance-shifts', [AttendanceController::class, 'storeShift'])->name('attendance-shifts.store');
        Route::get('/attendance-records', [AttendanceController::class, 'records'])->name('attendance-records.index');
        Route::get('/attendance-regularizations', [AttendanceController::class, 'regularizations'])->name('attendance-regularizations.index');
        Route::post('/attendance-regularizations', [AttendanceController::class, 'storeRegularization'])->name('attendance-regularizations.store');
        Route::patch('/attendance-regularizations/{regularization}/approve', [AttendanceController::class, 'approveRegularization'])->name('attendance-regularizations.approve');
        Route::patch('/attendance-regularizations/{regularization}/reject', [AttendanceController::class, 'rejectRegularization'])->name('attendance-regularizations.reject');
        Route::get('/attendance-rosters', [AttendanceRosterController::class, 'index'])->name('attendance-rosters.index');
        Route::post('/attendance-shift-assignments', [AttendanceRosterController::class, 'storeAssignment'])->name('attendance-shift-assignments.store');
        Route::post('/attendance-rosters', [AttendanceRosterController::class, 'storeRoster'])->name('attendance-rosters.store');
        Route::post('/attendance-rosters/{attendanceRoster}/entries', [AttendanceRosterController::class, 'storeEntry'])->name('attendance-rosters.entries.store');
        Route::patch('/attendance-rosters/{attendanceRoster}/publish', [AttendanceRosterController::class, 'publish'])->name('attendance-rosters.publish');
        Route::patch('/attendance-rosters/{attendanceRoster}/lock', [AttendanceRosterController::class, 'lock'])->name('attendance-rosters.lock');
        Route::patch('/attendance-rosters/{attendanceRoster}/reopen', [AttendanceRosterController::class, 'reopenRoster'])->name('attendance-rosters.reopen');
        Route::patch('/attendance-rosters/{attendanceRoster}/cancel', [AttendanceRosterController::class, 'cancelRoster'])->name('attendance-rosters.cancel');
        Route::post('/attendance-rotation-rules', [AttendanceRosterController::class, 'storeRotation'])->name('attendance-rotation-rules.store');
        Route::post('/attendance-rotation-rules/{attendanceRotationRule}/rosters/{attendanceRoster}/generate', [AttendanceRosterController::class, 'generateRotation'])->name('attendance-rotation-rules.generate');
        Route::post('/attendance-shift-swaps', [AttendanceRosterController::class, 'storeSwap'])->name('attendance-shift-swaps.store');
        Route::patch('/attendance-shift-swaps/{attendanceShiftSwapRequest}/approve', [AttendanceRosterController::class, 'approveSwap'])->name('attendance-shift-swaps.approve');
        Route::patch('/attendance-shift-swaps/{attendanceShiftSwapRequest}/reject', [AttendanceRosterController::class, 'rejectSwap'])->name('attendance-shift-swaps.reject');
        Route::patch('/attendance-shift-swaps/{attendanceShiftSwapRequest}/cancel', [AttendanceRosterController::class, 'cancelSwap'])->name('attendance-shift-swaps.cancel');
        Route::post('/attendance-periods/finalize', [AttendanceRosterController::class, 'finalizePeriod'])->name('attendance-periods.finalize');
        Route::patch('/attendance-periods/{attendancePeriodLock}/reopen', [AttendanceRosterController::class, 'reopenPeriod'])->name('attendance-periods.reopen');
        Route::get('/confirmation-cases', [EmployeeConfirmationController::class, 'index'])->name('confirmation-cases.index');
        Route::post('/confirmation-cases', [EmployeeConfirmationController::class, 'store'])->name('confirmation-cases.store');
        Route::patch('/confirmation-cases/{employeeConfirmationCase}/recommend', [EmployeeConfirmationController::class, 'recommend'])->name('confirmation-cases.recommend');
        Route::patch('/confirmation-cases/{employeeConfirmationCase}/decide', [EmployeeConfirmationController::class, 'decide'])->name('confirmation-cases.decide');
        Route::get('/separation-settlements', [EmployeeSeparationSettlementController::class, 'index'])->name('separation-settlements.index');
        Route::post('/separation-settlements', [EmployeeSeparationSettlementController::class, 'store'])->name('separation-settlements.store');
        Route::patch('/separation-settlements/{employeeSeparationSettlement}/hr-approve', [EmployeeSeparationSettlementController::class, 'hrApprove'])->name('separation-settlements.hr-approve');
        Route::patch('/separation-settlements/{employeeSeparationSettlement}/finance-approve', [EmployeeSeparationSettlementController::class, 'financeApprove'])->name('separation-settlements.finance-approve');
        Route::patch('/separation-settlements/{employeeSeparationSettlement}/complete', [EmployeeSeparationSettlementController::class, 'complete'])->name('separation-settlements.complete');
        Route::get('/exit-interviews/summary', [EmployeeExitInterviewController::class, 'summary'])->name('exit-interviews.summary');
        Route::get('/exit-interviews', [EmployeeExitInterviewController::class, 'index'])->name('exit-interviews.index');
        Route::post('/exit-interviews', [EmployeeExitInterviewController::class, 'store'])->name('exit-interviews.store');
        Route::patch('/exit-interviews/{employeeExitInterview}/submit', [EmployeeExitInterviewController::class, 'submit'])->name('exit-interviews.submit');
        Route::patch('/exit-interviews/{employeeExitInterview}/review', [EmployeeExitInterviewController::class, 'review'])->name('exit-interviews.review');
        Route::get('/lifecycle', [EmployeeLifecycleController::class, 'index'])->name('lifecycle.index');
        Route::get('/performance-dashboard', [PerformanceController::class, 'dashboard'])->name('performance-dashboard.index');
        Route::get('/performance-cycles', [PerformanceController::class, 'cycles'])->name('performance-cycles.index');
        Route::post('/performance-cycles', [PerformanceController::class, 'storeCycle'])->name('performance-cycles.store');
        Route::get('/performance-reviews', [PerformanceController::class, 'reviews'])->name('performance-reviews.index');
        Route::post('/performance-reviews', [PerformanceController::class, 'storeReview'])->name('performance-reviews.store');
        Route::patch('/performance-reviews/{performanceReview}/self-submit', [PerformanceController::class, 'submitSelf'])->name('performance-reviews.self-submit');
        Route::patch('/performance-reviews/{performanceReview}/manager-submit', [PerformanceController::class, 'submitManager'])->name('performance-reviews.manager-submit');
        Route::patch('/performance-reviews/{performanceReview}/calibrate', [PerformanceController::class, 'calibrate'])->name('performance-reviews.calibrate');
        Route::post('/performance-reviews/{performanceReview}/score-overrides', [PerformanceController::class, 'requestOverride'])->name('performance-reviews.score-overrides.store');
        Route::patch('/performance-score-overrides/{performanceScoreOverrideRequest}/approve', [PerformanceController::class, 'approveOverride'])->name('performance-score-overrides.approve');
        Route::patch('/performance-score-overrides/{performanceScoreOverrideRequest}/reject', [PerformanceController::class, 'rejectOverride'])->name('performance-score-overrides.reject');
        Route::patch('/performance-reviews/{performanceReview}/close', [PerformanceController::class, 'close'])->name('performance-reviews.close');
    });

    Route::prefix('payroll')->name('payroll.')->group(function (): void {
        Route::get('/components', [PayrollController::class, 'components'])->name('components.index');
        Route::get('/salary-structures', [PayrollController::class, 'structures'])->name('salary-structures.index');
        Route::get('/runs', [PayrollController::class, 'runs'])->name('runs.index');
        Route::post('/runs', [PayrollController::class, 'generate'])->name('runs.generate');
        Route::patch('/runs/{payrollRun}/approve', [PayrollController::class, 'approve'])->name('runs.approve');
        Route::get('/bank-transfer-batches', [PayrollController::class, 'bankTransferBatches'])->name('bank-transfer-batches.index');
        Route::post('/runs/{payrollRun}/bank-transfer-batches', [PayrollController::class, 'prepareBankTransferBatch'])->name('runs.bank-transfer-batches.store');
        Route::patch('/bank-transfer-batches/{payrollBankTransferBatch}/release', [PayrollController::class, 'releaseBankTransferBatch'])->name('bank-transfer-batches.release');
        Route::get('/tax-documents', [TaxDocumentController::class, 'index'])->name('tax-documents.index');
        Route::post('/tax-documents', [TaxDocumentController::class, 'store'])->name('tax-documents.store');
        Route::patch('/tax-documents/{employeeTaxDocument}/issue', [TaxDocumentController::class, 'issue'])->name('tax-documents.issue');
        Route::patch('/tax-documents/{employeeTaxDocument}/acknowledge', [TaxDocumentController::class, 'acknowledge'])->name('tax-documents.acknowledge');
        Route::get('/employee-tax-profiles', [EmployeeTaxProfileController::class, 'index'])->name('employee-tax-profiles.index');
        Route::get('/employee-tax-profiles/{employeeTaxProfile}', [EmployeeTaxProfileController::class, 'show'])->name('employee-tax-profiles.show');
        Route::patch('/employee-tax-profiles/{employeeTaxProfile}/verify', [EmployeeTaxProfileController::class, 'verify'])->name('employee-tax-profiles.verify');
        Route::patch('/employee-tax-profiles/{employeeTaxProfile}/lock', [EmployeeTaxProfileController::class, 'lock'])->name('employee-tax-profiles.lock');
        Route::get('/commission-rules', [CommissionController::class, 'rules'])->name('commission-rules.index');
        Route::post('/commission-rules', [CommissionController::class, 'storeRule'])->name('commission-rules.store');
        Route::get('/commission-runs', [CommissionController::class, 'runs'])->name('commission-runs.index');
        Route::post('/commission-runs', [CommissionController::class, 'storeRun'])->name('commission-runs.store');
        Route::patch('/commission-runs/{commissionRun}/approve', [CommissionController::class, 'approveRun'])->name('commission-runs.approve');
        Route::patch('/commission-runs/{commissionRun}/reject', [CommissionController::class, 'rejectRun'])->name('commission-runs.reject');
    });

    Route::prefix('recruitment')->name('recruitment.')->group(function (): void {
        Route::get('/source-summary', [RecruitmentController::class, 'sourceSummary'])->name('source-summary');
        Route::get('/job-openings', [RecruitmentController::class, 'openings'])->name('job-openings.index');
        Route::post('/job-openings', [RecruitmentController::class, 'storeOpening'])->name('job-openings.store');
        Route::patch('/job-openings/{jobOpening}/approve', [RecruitmentController::class, 'approveOpening'])->name('job-openings.approve');
        Route::patch('/job-openings/{jobOpening}/reject', [RecruitmentController::class, 'rejectOpening'])->name('job-openings.reject');
        Route::get('/pipeline', [RecruitmentController::class, 'pipeline'])->name('pipeline.index');
        Route::get('/candidates', [RecruitmentController::class, 'candidates'])->name('candidates.index');
        Route::post('/candidates', [RecruitmentController::class, 'storeCandidate'])->name('candidates.store');
        Route::patch('/candidates/{candidate}/stage', [RecruitmentController::class, 'updateCandidateStage'])->name('candidates.stage');
        Route::post('/candidates/{candidate}/convert-to-employee', [RecruitmentController::class, 'convertCandidateToEmployee'])->name('candidates.convert-to-employee');
        Route::get('/interviews', [RecruitmentController::class, 'interviews'])->name('interviews.index');
        Route::post('/interviews', [RecruitmentController::class, 'scheduleInterview'])->name('interviews.store');
        Route::patch('/interviews/{interview}/feedback', [RecruitmentController::class, 'submitInterviewFeedback'])->name('interviews.feedback');
        Route::get('/offers', [RecruitmentController::class, 'offers'])->name('offers.index');
        Route::post('/offers', [RecruitmentController::class, 'storeOffer'])->name('offers.store');
        Route::patch('/offers/{jobOffer}/release', [RecruitmentController::class, 'releaseOffer'])->name('offers.release');
    });

    Route::prefix('procurement')->name('procurement.')->group(function (): void {
        Route::get('/dashboard', [ProcurementController::class, 'dashboard'])->name('dashboard');
        Route::get('/vendors', [ProcurementController::class, 'vendors'])->name('vendors.index');
        Route::post('/vendors', [ProcurementController::class, 'storeVendor'])->name('vendors.store');
        Route::get('/vendors/{vendor}/performance', [ProcurementController::class, 'vendorPerformance'])->name('vendors.performance');
        Route::patch('/vendors/{vendor}', [ProcurementController::class, 'updateVendor'])->name('vendors.update');
        Route::patch('/vendors/{vendor}/performance-score', [VendorPerformanceScoreController::class, 'update'])->name('vendors.performance-score.update');
        Route::patch('/vendors/{vendor}/status', [ProcurementController::class, 'updateVendorStatus'])->name('vendors.status.update');
        Route::get('/requisitions', [ProcurementController::class, 'requisitions'])->name('requisitions.index');
        Route::post('/requisitions', [ProcurementController::class, 'storeRequisition'])->name('requisitions.store');
        Route::patch('/requisitions/{purchaseRequisition}/approve', [ProcurementController::class, 'approveRequisition'])->name('requisitions.approve');
        Route::get('/requisitions/{purchaseRequisition}/quote-comparison', [ProcurementController::class, 'quoteComparison'])->name('requisitions.quote-comparison');
        Route::get('/purchase-orders', [ProcurementController::class, 'purchaseOrders'])->name('purchase-orders.index');
        Route::post('/purchase-orders', [ProcurementController::class, 'storePurchaseOrder'])->name('purchase-orders.store');
        Route::patch('/purchase-orders/{purchaseOrder}/approve', [ProcurementController::class, 'approvePurchaseOrder'])->name('purchase-orders.approve');
        Route::get('/goods-receipts', [ProcurementController::class, 'goodsReceipts'])->name('goods-receipts.index');
        Route::post('/goods-receipts', [ProcurementController::class, 'storeGoodsReceipt'])->name('goods-receipts.store');
        Route::get('/stock-items', [ProcurementController::class, 'stockItems'])->name('stock-items.index');
        Route::post('/stock-issues', [ProcurementController::class, 'storeStockIssue'])->name('stock-issues.store');
        Route::post('/stock-returns', [ProcurementController::class, 'storeStockReturn'])->name('stock-returns.store');
        Route::post('/stock-transfers', [ProcurementController::class, 'storeStockTransfer'])->name('stock-transfers.store');
    });

    Route::prefix('construction')->name('construction.')->group(function (): void {
        Route::get('/milestones', [ConstructionController::class, 'milestones'])->name('milestones.index');
        Route::post('/milestones', [ConstructionController::class, 'storeMilestone'])->name('milestones.store');
        Route::get('/boq-items', [ConstructionController::class, 'boqItems'])->name('boq-items.index');
        Route::post('/boq-items', [ConstructionController::class, 'storeBoqItem'])->name('boq-items.store');
        Route::get('/daily-progress-reports', [ConstructionController::class, 'dailyReports'])->name('daily-progress-reports.index');
        Route::post('/daily-progress-reports', [ConstructionController::class, 'storeDailyReport'])->name('daily-progress-reports.store');
        Route::patch('/daily-progress-reports/{dailyProgressReport}/approve', [ConstructionController::class, 'approveDailyReport'])->name('daily-progress-reports.approve');
        Route::patch('/daily-progress-reports/{dailyProgressReport}/reject', [ConstructionController::class, 'rejectDailyReport'])->name('daily-progress-reports.reject');
        Route::get('/contractor-measurements', [ConstructionController::class, 'contractorMeasurements'])->name('contractor-measurements.index');
        Route::post('/contractor-measurements', [ConstructionController::class, 'storeContractorMeasurement'])->name('contractor-measurements.store');
        Route::patch('/contractor-measurements/{contractorMeasurement}/approve', [ConstructionController::class, 'approveContractorMeasurement'])->name('contractor-measurements.approve');
        Route::patch('/contractor-measurements/{contractorMeasurement}/reject', [ConstructionController::class, 'rejectContractorMeasurement'])->name('contractor-measurements.reject');
        Route::get('/contractor-bills', [ConstructionController::class, 'contractorBills'])->name('contractor-bills.index');
        Route::post('/contractor-bills', [ConstructionController::class, 'storeContractorBill'])->name('contractor-bills.store');
        Route::patch('/contractor-bills/{contractorBill}/approve', [ConstructionController::class, 'approveContractorBill'])->name('contractor-bills.approve');
        Route::patch('/contractor-bills/{contractorBill}/mark-paid', [ConstructionController::class, 'markContractorBillPaid'])->name('contractor-bills.mark-paid');
    });

    Route::prefix('legal')->name('legal.')->group(function (): void {
        Route::get('/rera-registrations', [LegalComplianceController::class, 'reraRegistrations'])->name('rera-registrations.index');
        Route::post('/rera-registrations', [LegalComplianceController::class, 'storeReraRegistration'])->name('rera-registrations.store');
        Route::patch('/rera-registrations/{reraRegistration}/verify', [LegalComplianceController::class, 'verifyReraRegistration'])->name('rera-registrations.verify');
        Route::get('/project-approvals', [LegalComplianceController::class, 'projectApprovals'])->name('project-approvals.index');
        Route::post('/project-approvals', [LegalComplianceController::class, 'storeProjectApproval'])->name('project-approvals.store');
        Route::patch('/project-approvals/{projectApproval}/verify', [LegalComplianceController::class, 'verifyProjectApproval'])->name('project-approvals.verify');
        Route::get('/compliance-obligations', [LegalComplianceController::class, 'complianceObligations'])->name('compliance-obligations.index');
        Route::post('/compliance-obligations', [LegalComplianceController::class, 'storeComplianceObligation'])->name('compliance-obligations.store');
        Route::patch('/compliance-obligations/{complianceObligation}/complete', [LegalComplianceController::class, 'completeComplianceObligation'])->name('compliance-obligations.complete');
    });

    Route::prefix('possession')->name('possession.')->group(function (): void {
        Route::get('/handovers', [PossessionHandoverController::class, 'handovers'])->name('handovers.index');
        Route::post('/handovers', [PossessionHandoverController::class, 'storeHandover'])->name('handovers.store');
        Route::patch('/handovers/{possessionHandover}/checklist', [PossessionHandoverController::class, 'updateChecklist'])->name('handovers.checklist.update');
        Route::patch('/handovers/{possessionHandover}/letter', [PossessionHandoverController::class, 'issueLetter'])->name('handovers.letter.issue');
        Route::patch('/handovers/{possessionHandover}/complete', [PossessionHandoverController::class, 'completeHandover'])->name('handovers.complete');
        Route::get('/snags', [PossessionHandoverController::class, 'snags'])->name('snags.index');
        Route::post('/snags', [PossessionHandoverController::class, 'storeSnag'])->name('snags.store');
        Route::patch('/snags/{handoverSnag}/resolve', [PossessionHandoverController::class, 'resolveSnag'])->name('snags.resolve');
    });

    Route::prefix('after-sales')->name('after-sales.')->group(function (): void {
        Route::get('/tickets', [AfterSalesController::class, 'tickets'])->name('tickets.index');
        Route::post('/tickets', [AfterSalesController::class, 'storeTicket'])->name('tickets.store');
        Route::patch('/tickets/{serviceTicket}/assign', [AfterSalesController::class, 'assignTicket'])->name('tickets.assign');
        Route::patch('/tickets/{serviceTicket}/resolve', [AfterSalesController::class, 'resolveTicket'])->name('tickets.resolve');
        Route::patch('/tickets/{serviceTicket}/close', [AfterSalesController::class, 'closeTicket'])->name('tickets.close');
        Route::get('/work-orders', [AfterSalesController::class, 'workOrders'])->name('work-orders.index');
        Route::post('/work-orders', [AfterSalesController::class, 'storeWorkOrder'])->name('work-orders.store');
        Route::patch('/work-orders/{maintenanceWorkOrder}/complete', [AfterSalesController::class, 'completeWorkOrder'])->name('work-orders.complete');
    });

    Route::prefix('maintenance')->name('maintenance.')->group(function (): void {
        Route::get('/societies', [MaintenanceSocietyController::class, 'societies'])->name('societies.index');
        Route::post('/societies', [MaintenanceSocietyController::class, 'storeSociety'])->name('societies.store');
        Route::patch('/societies/{societyFormation}/status', [MaintenanceSocietyController::class, 'updateSocietyStatus'])->name('societies.status');
        Route::get('/handover-items', [MaintenanceSocietyController::class, 'handoverItems'])->name('handover-items.index');
        Route::patch('/handover-items/{commonAreaHandoverItem}', [MaintenanceSocietyController::class, 'updateHandoverItem'])->name('handover-items.update');
        Route::patch('/handover-items/{commonAreaHandoverItem}/sign-off', [MaintenanceSocietyController::class, 'signOffHandoverItem'])->name('handover-items.sign-off');
        Route::get('/dues', [MaintenanceSocietyController::class, 'dues'])->name('dues.index');
        Route::post('/dues', [MaintenanceSocietyController::class, 'storeDue'])->name('dues.store');
        Route::patch('/dues/{maintenanceDue}/mark-paid', [MaintenanceSocietyController::class, 'markDuePaid'])->name('dues.mark-paid');
        Route::patch('/dues/{maintenanceDue}/remind', [MaintenanceSocietyController::class, 'remindDue'])->name('dues.remind');
    });

    Route::prefix('governance')->name('governance.')->group(function (): void {
        Route::get('/audit-events', [AuditTrailController::class, 'index'])->name('audit-events.index');
        Route::get('/audit-events/export', [AuditTrailController::class, 'export'])->name('audit-events.export');
        Route::get('/management-summary', [ManagementReportController::class, 'summary'])->name('management-summary.show');
        Route::get('/report-register', [ManagementReportController::class, 'register'])->name('report-register.index');
        Route::post('/report-pins', [ManagementReportController::class, 'storePin'])->name('report-pins.store');
        Route::delete('/report-pins/{reportPin}', [ManagementReportController::class, 'destroyPin'])->name('report-pins.destroy');
        Route::post('/report-schedules', [ManagementReportController::class, 'storeSchedule'])->name('report-schedules.store');
        Route::patch('/report-schedules/{reportSchedule}/archive', [ManagementReportController::class, 'archiveSchedule'])->name('report-schedules.archive');
    });

    Route::prefix('notifications')->name('notifications.')->group(function (): void {
        Route::get('/', [NotificationCenterController::class, 'index'])->name('index');
        Route::get('/summary', [NotificationCenterController::class, 'summary'])->name('summary');
        Route::patch('/read-all', [NotificationCenterController::class, 'markAllRead'])->name('read-all');
        Route::patch('/{userNotification}/read', [NotificationCenterController::class, 'markRead'])->name('read');
        Route::patch('/{userNotification}/archive', [NotificationCenterController::class, 'archive'])->name('archive');
    });

    Route::prefix('collaboration')->name('collaboration.')->group(function (): void {
        Route::get('/tasks', [CollaborationController::class, 'tasks'])->name('tasks.index');
        Route::get('/tasks/export', [CollaborationController::class, 'exportTasks'])->name('tasks.export');
        Route::post('/tasks', [CollaborationController::class, 'storeTask'])->name('tasks.store');
        Route::post('/tasks/{workTask}/duplicate', [WorkTaskLifecycleController::class, 'duplicate'])->name('tasks.duplicate');
        Route::post('/tasks/{workTask}/attachments', [WorkTaskLifecycleController::class, 'storeAttachment'])->name('tasks.attachments.store');
        Route::get('/tasks/{workTask}/attachments/{workTaskAttachment}', [WorkTaskLifecycleController::class, 'downloadAttachment'])->name('tasks.attachments.download');
        Route::get('/tasks/{workTask}/attachments/{workTaskAttachment}/preview', [WorkTaskLifecycleController::class, 'previewAttachment'])->name('tasks.attachments.preview');
        Route::delete('/tasks/{workTask}/attachments/{workTaskAttachment}', [WorkTaskLifecycleController::class, 'destroyAttachment'])->name('tasks.attachments.destroy');
        Route::patch('/tasks/completion-approvals/{workTaskCompletionApproval}/decide', [WorkTaskLifecycleController::class, 'decideCompletion'])->name('tasks.completion-approvals.decide');
        Route::patch('/tasks/{workTask}/reopen', [WorkTaskLifecycleController::class, 'reopen'])->name('tasks.reopen');
        Route::patch('/tasks/{workTask}/recurrence', [WorkTaskLifecycleController::class, 'updateRecurrence'])->name('tasks.recurrence.update');
        Route::patch('/tasks/bulk/update', [CollaborationController::class, 'bulkUpdateTasks'])->name('tasks.bulk-update');
        Route::patch('/tasks/bulk/archive', [CollaborationController::class, 'bulkArchiveTasks'])->name('tasks.bulk-archive');
        Route::patch('/tasks/transfer-requests/{workTaskTransferRequest}/resolve', [CollaborationController::class, 'resolveTaskTransferApproval'])->name('tasks.transfer-requests.resolve');
        Route::patch('/tasks/{workTask}', [CollaborationController::class, 'updateTask'])->name('tasks.update');
        Route::patch('/tasks/{workTask}/assign', [CollaborationController::class, 'assignTask'])->name('tasks.assign');
        Route::post('/tasks/{workTask}/transfer-requests', [CollaborationController::class, 'requestTaskTransferApproval'])->name('tasks.transfer-requests.store');
        Route::patch('/tasks/{workTask}/archive', [CollaborationController::class, 'archiveTask'])->name('tasks.archive');
        Route::patch('/tasks/{workTask}/status', [CollaborationController::class, 'updateTaskStatus'])->name('tasks.status.update');
        Route::patch('/tasks/{workTask}/watcher', [CollaborationController::class, 'updateTaskWatcher'])->name('tasks.watcher.update');
        Route::patch('/tasks/{workTask}/dependencies', [CollaborationController::class, 'updateTaskDependencies'])->name('tasks.dependencies.update');
        Route::post('/tasks/{workTask}/comments', [CollaborationController::class, 'storeTaskComment'])->name('tasks.comments.store');
        Route::patch('/tasks/{workTask}/checklist', [CollaborationController::class, 'updateTaskChecklist'])->name('tasks.checklist.update');
        Route::post('/tasks/{workTask}/subtasks', [CollaborationController::class, 'storeTaskSubtask'])->name('tasks.subtasks.store');
        Route::patch('/tasks/{workTask}/subtasks/{workTaskSubtask}', [CollaborationController::class, 'updateTaskSubtask'])->name('tasks.subtasks.update');
        Route::post('/tasks/{workTask}/time-logs', [CollaborationController::class, 'storeTaskTimeLog'])->name('tasks.time-logs.store');
        Route::get('/calendar-events', [CollaborationController::class, 'calendarEvents'])->name('calendar-events.index');
        Route::post('/calendar-events', [CollaborationController::class, 'storeCalendarEvent'])->name('calendar-events.store');
        Route::patch('/calendar-events/{calendarEvent}', [CollaborationController::class, 'updateCalendarEvent'])->name('calendar-events.update');
        Route::patch('/calendar-events/{calendarEvent}/complete', [CollaborationController::class, 'completeCalendarEvent'])->name('calendar-events.complete');
        Route::patch('/calendar-events/{calendarEvent}/cancel', [CollaborationController::class, 'cancelCalendarEvent'])->name('calendar-events.cancel');
        Route::patch('/calendar-events/{calendarEvent}/response', [\App\Http\Controllers\Collaboration\CalendarInvitationController::class, 'respond'])->name('calendar-events.response');
        Route::post('/calendar-events/{calendarEvent}/attachments', [\App\Http\Controllers\Collaboration\CalendarAttachmentController::class, 'store'])->name('calendar-events.attachments.store');
        Route::get('/calendar-events/{calendarEvent}/attachments/{calendarEventAttachment}/preview', [\App\Http\Controllers\Collaboration\CalendarAttachmentController::class, 'preview'])->name('calendar-events.attachments.preview');
        Route::get('/calendar-events/{calendarEvent}/attachments/{calendarEventAttachment}/download', [\App\Http\Controllers\Collaboration\CalendarAttachmentController::class, 'download'])->name('calendar-events.attachments.download');
        Route::delete('/calendar-events/{calendarEvent}/attachments/{calendarEventAttachment}', [\App\Http\Controllers\Collaboration\CalendarAttachmentController::class, 'destroy'])->name('calendar-events.attachments.destroy');
        Route::delete('/calendar-events/{calendarEvent}', [CollaborationController::class, 'deleteCalendarEvent'])->name('calendar-events.destroy');
        Route::get('/chat', [CollaborationController::class, 'chat'])->name('chat.index');
        Route::get('/chat/conversations', [CollaborationController::class, 'chatConversations'])->name('chat.conversations.index');
        Route::get('/chat/sidebar', [CollaborationController::class, 'chatConversationSidebar'])->name('chat.sidebar');
        Route::post('/chat/conversations', [CollaborationController::class, 'storeChatConversation'])->name('chat.conversations.store');
        Route::get('/chat/conversations/{chatConversation}/messages', [CollaborationController::class, 'chatConversationMessages'])->name('chat.conversations.messages.index');
        Route::get('/chat/conversations/{chatConversation}/timeline', [CollaborationController::class, 'chatConversationTimeline'])->name('chat.conversations.timeline');
        Route::post('/chat/conversations/{chatConversation}/messages', [CollaborationController::class, 'storeChatConversationMessage'])->name('chat.conversations.messages.store');
        Route::post('/chat/conversations/{chatConversation}/polls', [CollaborationController::class, 'storeChatPoll'])->name('chat.conversations.polls.store');
        Route::post('/chat/polls/{poll}/votes', [CollaborationController::class, 'voteChatPoll'])->name('chat.polls.votes.store');
        Route::patch('/chat/polls/{poll}/close', [CollaborationController::class, 'closeChatPoll'])->name('chat.polls.close');
        Route::get('/chat/attachments/{attachment}/download', [CollaborationController::class, 'downloadChatAttachment'])->name('chat.attachments.download');
        Route::get('/chat/attachments/{attachment}/preview', [CollaborationController::class, 'previewChatAttachment'])->name('chat.attachments.preview');
        Route::patch('/chat/messages/{chatMessage}/reactions', [CollaborationController::class, 'updateChatMessageReaction'])->name('chat.messages.reactions.update');
        Route::delete('/chat/messages/{chatMessage}', [CollaborationController::class, 'destroyChatMessage'])->name('chat.messages.destroy');
        Route::patch('/chat/conversations/{chatConversation}/read', [CollaborationController::class, 'markChatConversationRead'])->name('chat.conversations.read');
        Route::patch('/chat/conversations/{chatConversation}/archive', [CollaborationController::class, 'archiveChatConversation'])->name('chat.conversations.archive');
        Route::patch('/chat/conversations/{chatConversation}', [CollaborationController::class, 'updateChatConversation'])->name('chat.conversations.update');
        Route::post('/chat/conversations/{chatConversation}/members', [CollaborationController::class, 'addChatConversationMembers'])->name('chat.conversations.members.store');
        Route::delete('/chat/conversations/{chatConversation}/members/{user}', [CollaborationController::class, 'removeChatConversationMember'])->name('chat.conversations.members.destroy');
        Route::get('/messages', [CollaborationController::class, 'messages'])->name('messages.index');
        Route::get('/messages/export', [CollaborationController::class, 'exportMessages'])->name('messages.export');
        Route::post('/messages', [CollaborationController::class, 'storeMessage'])->name('messages.store');
        Route::patch('/messages/{collaborationMessage}/read', [CollaborationController::class, 'markMessageRead'])->name('messages.read');
        Route::patch('/messages/{collaborationMessage}/archive', [CollaborationController::class, 'archiveMessage'])->name('messages.archive');
        Route::patch('/messages/{collaborationMessage}/cancel-scheduled', [CollaborationController::class, 'cancelScheduledMessage'])->name('messages.cancel-scheduled');
        Route::patch('/messages/{collaborationMessage}/crm-link', [CollaborationController::class, 'updateMessageCrmLink'])->name('messages.crm-link.update');
        Route::patch('/messages/{collaborationMessage}/state', [CollaborationController::class, 'updateMessageState'])->name('messages.state.update');
        Route::patch('/messages/{collaborationMessage}/reactions', [CollaborationController::class, 'updateMessageReaction'])->name('messages.reactions.update');
    });

    Route::prefix('mailbox')->name('mailbox.')->group(function (): void {
        Route::get('/', [MailboxAccountController::class, 'workspace'])->name('index');
        Route::get('/accounts', [MailboxAccountController::class, 'index'])->name('accounts.index');
        Route::post('/accounts', [MailboxAccountController::class, 'store'])->middleware('throttle:10,1')->name('accounts.store');
        Route::delete('/accounts/{mailboxAccount}', [MailboxAccountController::class, 'destroy'])->name('accounts.destroy');
        Route::put('/accounts/{mailboxAccount}', [MailboxAccountController::class, 'update'])->name('accounts.update');
        Route::get('/accounts/{mailboxAccount}/avatar', [MailboxAccountController::class, 'avatar'])->name('accounts.avatar');
        Route::patch('/accounts/{mailboxAccount}/change-password', [MailboxAccountController::class, 'changePassword'])->name('accounts.change-password');
        Route::post('/accounts/{mailboxAccount}/sync', [MailboxAccountController::class, 'sync'])->middleware('throttle:30,1')->name('accounts.sync');
        Route::post('/accounts/{mailboxAccount}/sync-json', [MailboxAccountController::class, 'syncJson'])->middleware('throttle:60,1')->name('accounts.sync-json');
        Route::post('/accounts/{mailboxAccount}/bulk-state', [MailboxAccountController::class, 'bulkState'])->name('accounts.bulk-state');
        Route::post('/accounts/{mailboxAccount}/assignments', [MailboxAccountController::class, 'assign'])->name('accounts.assignments.store');
        Route::delete('/accounts/{mailboxAccount}/assignments/{mailboxAccountAssignment}', [MailboxAccountController::class, 'unassign'])->name('accounts.assignments.destroy');
        Route::get('/accounts/{mailboxAccount}', [MailboxAccountController::class, 'show'])->name('external.show');
        Route::post('/accounts/{mailboxAccount}/send', [MailboxAccountController::class, 'send'])->middleware('throttle:30,1')->name('external.send');
        Route::get('/accounts/{mailboxAccount}/drafts', [MailboxAccountController::class, 'drafts'])->name('drafts.index');
        Route::post('/accounts/{mailboxAccount}/drafts', [MailboxAccountController::class, 'saveDraft'])->middleware('throttle:120,1')->name('drafts.store');
        Route::delete('/accounts/{mailboxAccount}/drafts/{mailboxOutboxMessage}', [MailboxAccountController::class, 'discardDraft'])->name('drafts.destroy');
        Route::patch('/messages/{mailboxEmail}/state', [MailboxAccountController::class, 'state'])->name('external.state');
        Route::get('/attachments/{mailboxAttachment}', [MailboxAccountController::class, 'attachment'])->name('attachments.download');
    });

    Route::prefix('settings')->name('settings.')->group(function (): void {
        Route::get('/system-settings', [SystemSettingController::class, 'index'])->name('system-settings.index');
        Route::post('/system-settings', [SystemSettingController::class, 'store'])->name('system-settings.store');
        Route::patch('/system-settings/{systemSetting}/approve', [SystemSettingController::class, 'approve'])->name('system-settings.approve');
        Route::get('/data-imports', [DataImportController::class, 'index'])->name('data-imports.index');
        Route::post('/data-imports/preview', [DataImportController::class, 'preview'])->name('data-imports.preview');
        Route::post('/data-imports/{dataImportBatch}/post', [DataImportController::class, 'post'])->name('data-imports.post');
    });

    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/companies', [CompanyAdministrationController::class, 'index'])->name('companies.index');
        Route::post('/companies', [CompanyAdministrationController::class, 'store'])->name('companies.store');
        Route::get('/roles', [RoleAdministrationController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleAdministrationController::class, 'store'])->name('roles.store');
        Route::patch('/roles/{role}', [RoleAdministrationController::class, 'update'])->name('roles.update');
        Route::get('/users', [UserAdministrationController::class, 'index'])->name('users.index');
        Route::post('/users', [UserAdministrationController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}/access', [UserAdministrationController::class, 'updateAccess'])->name('users.access.update');
    });

    Route::prefix('partner')->name('partner.')->group(function (): void {
        Route::get('/summary', [PartnerDashboardController::class, 'show'])->name('summary');
        Route::get('/leads', [PartnerLeadController::class, 'index'])->name('leads.index');
        Route::get('/bookings', [PartnerBookingController::class, 'index'])->name('bookings.index');
    });

    Route::prefix('buyer')->name('buyer.')->group(function (): void {
        Route::get('/summary', [BuyerPortalController::class, 'summary'])->name('summary');
        Route::get('/bookings', [BuyerPortalController::class, 'bookings'])->name('bookings.index');
        Route::get('/receipts', [BuyerPortalController::class, 'receipts'])->name('receipts.index');
        Route::get('/payment-requests', [BuyerPortalController::class, 'paymentRequests'])->name('payment-requests.index');
        Route::patch('/payment-requests/{paymentRequest}/pay', [BuyerPortalController::class, 'payPaymentRequest'])->name('payment-requests.pay');
        Route::get('/documents', [BuyerPortalController::class, 'documents'])->name('documents.index');
        Route::get('/service-tickets', [BuyerPortalController::class, 'tickets'])->name('service-tickets.index');
        Route::post('/service-tickets', [BuyerPortalController::class, 'storeTicket'])->name('service-tickets.store');
        Route::patch('/service-tickets/{serviceTicket}/close', [BuyerPortalController::class, 'closeTicket'])->name('service-tickets.close');
    });
});

Route::get('/calendar/invitations/{calendarEventAttendee}', [\App\Http\Controllers\Collaboration\CalendarInvitationController::class, 'showGuest'])->middleware('signed')->name('calendar.guest-invitations.show');
Route::post('/calendar/invitations/{calendarEventAttendee}', [\App\Http\Controllers\Collaboration\CalendarInvitationController::class, 'respondGuest'])->middleware(['signed','throttle:20,1'])->name('calendar.guest-invitations.respond');

Route::get('/errors/{code}', function (string $code) {
    if (view()->exists("errors.{$code}")) {
        return response()->view("errors.{$code}", [], (int) $code);
    }
    abort(404);
})->where('code', '[0-9]+');
