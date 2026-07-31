<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\AttendancePeriodLock;
use App\Models\AttendanceRegularizationRequest;
use App\Models\AttendanceRoster;
use App\Models\AttendanceRotationRule;
use App\Models\AttendanceShiftSwapRequest;
use App\Models\BoqItem;
use App\Models\CalendarEvent;
use App\Models\Candidate;
use App\Models\ChatConversation;
use App\Models\CollaborationMessage;
use App\Models\CollectionReceipt;
use App\Models\CommissionRule;
use App\Models\CommissionRun;
use App\Models\Company;
use App\Models\ComplianceObligation;
use App\Models\CommonAreaHandoverItem;
use App\Models\ConstructionMilestone;
use App\Models\ContractorBill;
use App\Models\ContractorMeasurement;
use App\Models\DailyProgressReport;
use App\Models\DataImportBatch;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\EmployeeAsset;
use App\Models\EmployeeConfirmationCase;
use App\Models\EmployeeExitInterview;
use App\Models\EmployeeLoan;
use App\Models\EmployeePolicyAcknowledgement;
use App\Models\EmployeeSeparationSettlement;
use App\Models\EmployeeTaxDocument;
use App\Models\EmployeeTaxProfile;
use App\Models\ExpenseClaim;
use App\Models\FinancialVoucher;
use App\Models\GstEntry;
use App\Models\GstReturnPeriod;
use App\Models\HrHelpdeskTicket;
use App\Models\Interview;
use App\Models\JobOffer;
use App\Models\JobOpening;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadQualification;
use App\Models\LeaveEncashment;
use App\Models\LeaveProcessingRun;
use App\Models\LeaveRequest;
use App\Models\MaintenanceDue;
use App\Models\MaintenanceWorkOrder;
use App\Models\ManagedDocument;
use App\Models\MarketingCampaign;
use App\Models\PayrollBankTransferBatch;
use App\Models\PayrollRun;
use App\Models\HandoverSnag;
use App\Models\PaymentRequest;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\PossessionHandover;
use App\Models\ProspectInquiry;
use App\Models\Project;
use App\Models\ProjectApproval;
use App\Models\ProjectTeamAssignment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\ProjectUnit;
use App\Models\ReraRegistration;
use App\Models\Role;
use App\Models\ScoringRule;
use App\Models\ScoreSnapshot;
use App\Models\ServiceTicket;
use App\Models\SiteVisit;
use App\Models\SocietyFormation;
use App\Models\SystemSetting;
use App\Models\UnitPriceVersion;
use App\Models\User;
use App\Models\MailboxAccount;
use App\Models\UserNotification;
use App\Models\Vendor;
use App\Models\WorkTask;
use App\Policies\BookingPolicy;
use App\Policies\AttendancePeriodLockPolicy;
use App\Policies\AttendanceRegularizationPolicy;
use App\Policies\AttendanceRosterPolicy;
use App\Policies\AttendanceRotationRulePolicy;
use App\Policies\AttendanceShiftSwapRequestPolicy;
use App\Policies\BoqItemPolicy;
use App\Policies\CalendarEventPolicy;
use App\Policies\CandidatePolicy;
use App\Policies\ChatConversationPolicy;
use App\Policies\CollaborationMessagePolicy;
use App\Policies\CollectionReceiptPolicy;
use App\Policies\CommissionRulePolicy;
use App\Policies\CommissionRunPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\ComplianceObligationPolicy;
use App\Policies\CommonAreaHandoverItemPolicy;
use App\Policies\ConstructionMilestonePolicy;
use App\Policies\ContractorBillPolicy;
use App\Policies\ContractorMeasurementPolicy;
use App\Policies\DailyProgressReportPolicy;
use App\Policies\DataImportBatchPolicy;
use App\Policies\InterviewPolicy;
use App\Policies\JobOfferPolicy;
use App\Policies\JobOpeningPolicy;
use App\Policies\LeadActivityPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\EmployeeShiftAssignmentPolicy;
use App\Policies\EmployeeAssetPolicy;
use App\Policies\EmployeeConfirmationCasePolicy;
use App\Policies\EmployeeExitInterviewPolicy;
use App\Policies\EmployeeLoanPolicy;
use App\Policies\EmployeePolicyAcknowledgementPolicy;
use App\Policies\EmployeeSeparationSettlementPolicy;
use App\Policies\EmployeeTaxDocumentPolicy;
use App\Policies\EmployeeTaxProfilePolicy;
use App\Policies\ExpenseClaimPolicy;
use App\Policies\FinancialVoucherPolicy;
use App\Policies\GstEntryPolicy;
use App\Policies\GstReturnPeriodPolicy;
use App\Policies\HrHelpdeskTicketPolicy;
use App\Policies\LeadPolicy;
use App\Policies\LeadQualificationPolicy;
use App\Policies\LeaveEncashmentPolicy;
use App\Policies\LeaveProcessingRunPolicy;
use App\Policies\LeaveRequestPolicy;
use App\Policies\MaintenanceDuePolicy;
use App\Policies\MaintenanceWorkOrderPolicy;
use App\Policies\ManagedDocumentPolicy;
use App\Policies\MarketingCampaignPolicy;
use App\Policies\PayrollBankTransferBatchPolicy;
use App\Policies\PayrollRunPolicy;
use App\Policies\HandoverSnagPolicy;
use App\Policies\PaymentRequestPolicy;
use App\Policies\PerformanceCyclePolicy;
use App\Policies\PerformanceReviewPolicy;
use App\Policies\PossessionHandoverPolicy;
use App\Policies\ProspectInquiryPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\ProjectApprovalPolicy;
use App\Policies\ProjectTeamAssignmentPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\PurchaseRequisitionPolicy;
use App\Policies\ProjectUnitPolicy;
use App\Policies\ReraRegistrationPolicy;
use App\Policies\RolePolicy;
use App\Policies\ScoringRulePolicy;
use App\Policies\ScoreSnapshotPolicy;
use App\Policies\ServiceTicketPolicy;
use App\Policies\SiteVisitPolicy;
use App\Policies\SocietyFormationPolicy;
use App\Policies\SystemSettingPolicy;
use App\Policies\UnitPriceVersionPolicy;
use App\Policies\UserPolicy;
use App\Policies\UserNotificationPolicy;
use App\Policies\VendorPolicy;
use App\Policies\WorkTaskPolicy;
use App\Observers\WorkTaskObserver;
use App\Services\Security\CompanyScopeService;
use App\View\Composers\Builder360ShellComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use App\Domain\Mailbox\Contracts\ImapMailboxGateway;
use App\Domain\Mailbox\Contracts\SmtpMailboxGateway;
use App\Infrastructure\Mailbox\WebklexImapMailboxGateway;
use App\Infrastructure\Mailbox\SymfonySmtpMailboxGateway;
use App\Domain\Scoring\Support\LogicCenterPermissions;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ImapMailboxGateway::class, WebklexImapMailboxGateway::class);
        $this->app->bind(SmtpMailboxGateway::class, SymfonySmtpMailboxGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        WorkTask::observe(WorkTaskObserver::class);
        View::composer('layouts.builder360-classic', Builder360ShellComposer::class);

        Gate::define('use-global-search', static fn (User $user): bool => $user->status === 'active');
        Gate::define('change-theme', static fn (User $user): bool => $user->status === 'active');
        Gate::define('view-builder360-dashboard', static fn (User $user): bool => $user->status === 'active');

        RateLimiter::for('erp-read', function (Request $request): Limit {
            $maxAttempts = max(1, (int) config('security.rate_limits.erp_read_per_minute', 1200));
            $actor = $request->user()?->getAuthIdentifier() ?: 'guest';

            return Limit::perMinute($maxAttempts)->by($actor.'|'.$request->ip());
        });

        Gate::before(function (User $user, string $ability, array $arguments = []) {
            if (! $user->hasPermission('*')) {
                return null;
            }

            // Company mailbox credentials and messages are assignment-scoped.
            // A wildcard business role must still pass the mailbox policy.
            if (collect($arguments)->contains(fn (mixed $argument): bool => $argument instanceof MailboxAccount)) {
                return null;
            }

            // Employee tax inputs contain sensitive declaration and prior-employer
            // amounts. A wildcard technical administrator must still satisfy the
            // dedicated payroll/compliance policy for model and class abilities.
            if (collect($arguments)->contains(fn (mixed $argument): bool => $argument instanceof EmployeeTaxProfile
                || $argument === EmployeeTaxProfile::class)) {
                return null;
            }

            // Tax-declaration proofs remain sensitive even though they use the
            // shared private document store. Defer these instance checks to the
            // proof-aware document policy instead of granting wildcard access.
            if (collect($arguments)->contains(fn (mixed $argument): bool => $argument instanceof ManagedDocument
                && $argument->taxDeclarations()->exists())) {
                return null;
            }

            return app(CompanyScopeService::class)->allowsWildcardAbility($ability, $arguments);
        });

        foreach (array_merge([
            'after_sales.approve',
            'after_sales.manage',
            'after_sales.view',
            'assets.manage',
            'assets.view',
            'attendance.approve',
            'attendance.manage',
            'attendance.request',
            'attendance.view',
            'audit.view',
            'booking.manage',
            'booking.view',
            'buyer.view',
            'claims.approve',
            'claims.manage',
            'claims.view',
            'collections.approve',
            'collections.manage',
            'collections.view',
            'collaboration.manage',
            'collaboration.view',
            'construction.approve',
            'construction.manage',
            'construction.view',
            'crm.manage',
            'crm.view',
            'documents.approve',
            'documents.manage',
            'documents.view',
            'employee.self_service',
            'finance.approve',
            'finance.manage',
            'finance.view',
            'helpdesk.manage',
            'helpdesk.view',
            'hr.manage',
            'hr.view',
            'inventory.view',
            'leave.approve',
            'leave.manage',
            'leave.request',
            'leave.view',
            'legal.approve',
            'legal.manage',
            'legal.view',
            'loans.approve',
            'loans.manage',
            'loans.view',
            'partner.portal',
            'payroll.approve',
            'payroll.manage',
            'payroll.view',
            'performance.approve',
            'performance.manage',
            'performance.view',
            'possession.approve',
            'possession.manage',
            'possession.view',
            'procurement.approve',
            'procurement.manage',
            'procurement.view',
            'recruitment.approve',
            'recruitment.manage',
            'recruitment.view',
            'reports.view',
            'roles.manage',
            'roles.view',
            'settings.approve',
            'settings.manage',
            'settings.view',
            'scoring.approve',
            'scoring.manage',
            'scoring.override',
            'scoring.recalculate',
            'scoring.view',
            'users.manage',
            'users.view',
        ], LogicCenterPermissions::all()) as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->hasPermission($permission));
        }

        Gate::policy(Candidate::class, CandidatePolicy::class);
        Gate::policy(Interview::class, InterviewPolicy::class);
        Gate::policy(JobOffer::class, JobOfferPolicy::class);
        Gate::policy(JobOpening::class, JobOpeningPolicy::class);
        Gate::policy(Lead::class, LeadPolicy::class);
        Gate::policy(LeadActivity::class, LeadActivityPolicy::class);
        Gate::policy(LeadQualification::class, LeadQualificationPolicy::class);
        Gate::policy(MarketingCampaign::class, MarketingCampaignPolicy::class);
        Gate::policy(ProspectInquiry::class, ProspectInquiryPolicy::class);
        Gate::policy(SiteVisit::class, SiteVisitPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(ProjectTeamAssignment::class, ProjectTeamAssignmentPolicy::class);
        Gate::policy(ProjectUnit::class, ProjectUnitPolicy::class);
        Gate::policy(UnitPriceVersion::class, UnitPriceVersionPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(CollectionReceipt::class, CollectionReceiptPolicy::class);
        Gate::policy(PaymentRequest::class, PaymentRequestPolicy::class);
        Gate::policy(CommissionRule::class, CommissionRulePolicy::class);
        Gate::policy(CommissionRun::class, CommissionRunPolicy::class);
        Gate::policy(ManagedDocument::class, ManagedDocumentPolicy::class);
        Gate::policy(PayrollBankTransferBatch::class, PayrollBankTransferBatchPolicy::class);
        Gate::policy(LeaveProcessingRun::class, LeaveProcessingRunPolicy::class);
        Gate::policy(LeaveEncashment::class, LeaveEncashmentPolicy::class);
        Gate::policy(LeaveRequest::class, LeaveRequestPolicy::class);
        Gate::policy(AttendanceRoster::class, AttendanceRosterPolicy::class);
        Gate::policy(AttendanceRotationRule::class, AttendanceRotationRulePolicy::class);
        Gate::policy(AttendanceShiftSwapRequest::class, AttendanceShiftSwapRequestPolicy::class);
        Gate::policy(AttendancePeriodLock::class, AttendancePeriodLockPolicy::class);
        Gate::policy(AttendanceRegularizationRequest::class, AttendanceRegularizationPolicy::class);
        Gate::policy(EmployeeShiftAssignment::class, EmployeeShiftAssignmentPolicy::class);
        Gate::policy(PayrollRun::class, PayrollRunPolicy::class);
        Gate::policy(PurchaseRequisition::class, PurchaseRequisitionPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(BoqItem::class, BoqItemPolicy::class);
        Gate::policy(ContractorBill::class, ContractorBillPolicy::class);
        Gate::policy(ContractorMeasurement::class, ContractorMeasurementPolicy::class);
        Gate::policy(ConstructionMilestone::class, ConstructionMilestonePolicy::class);
        Gate::policy(DailyProgressReport::class, DailyProgressReportPolicy::class);
        Gate::policy(ReraRegistration::class, ReraRegistrationPolicy::class);
        Gate::policy(ProjectApproval::class, ProjectApprovalPolicy::class);
        Gate::policy(ComplianceObligation::class, ComplianceObligationPolicy::class);
        Gate::policy(PossessionHandover::class, PossessionHandoverPolicy::class);
        Gate::policy(HandoverSnag::class, HandoverSnagPolicy::class);
        Gate::policy(ServiceTicket::class, ServiceTicketPolicy::class);
        Gate::policy(MaintenanceWorkOrder::class, MaintenanceWorkOrderPolicy::class);
        Gate::policy(SocietyFormation::class, SocietyFormationPolicy::class);
        Gate::policy(CommonAreaHandoverItem::class, CommonAreaHandoverItemPolicy::class);
        Gate::policy(MaintenanceDue::class, MaintenanceDuePolicy::class);
        Gate::policy(UserNotification::class, UserNotificationPolicy::class);
        Gate::policy(SystemSetting::class, SystemSettingPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(ScoringRule::class, ScoringRulePolicy::class);
        Gate::policy(ScoreSnapshot::class, ScoreSnapshotPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(EmployeeAsset::class, EmployeeAssetPolicy::class);
        Gate::policy(EmployeeConfirmationCase::class, EmployeeConfirmationCasePolicy::class);
        Gate::policy(EmployeeExitInterview::class, EmployeeExitInterviewPolicy::class);
        Gate::policy(EmployeeSeparationSettlement::class, EmployeeSeparationSettlementPolicy::class);
        Gate::policy(EmployeeTaxDocument::class, EmployeeTaxDocumentPolicy::class);
        Gate::policy(EmployeeTaxProfile::class, EmployeeTaxProfilePolicy::class);
        Gate::policy(ExpenseClaim::class, ExpenseClaimPolicy::class);
        Gate::policy(EmployeeLoan::class, EmployeeLoanPolicy::class);
        Gate::policy(EmployeePolicyAcknowledgement::class, EmployeePolicyAcknowledgementPolicy::class);
        Gate::policy(HrHelpdeskTicket::class, HrHelpdeskTicketPolicy::class);
        Gate::policy(WorkTask::class, WorkTaskPolicy::class);
        Gate::policy(CalendarEvent::class, CalendarEventPolicy::class);
        Gate::policy(ChatConversation::class, ChatConversationPolicy::class);
        Gate::policy(CollaborationMessage::class, CollaborationMessagePolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(DataImportBatch::class, DataImportBatchPolicy::class);
        Gate::policy(FinancialVoucher::class, FinancialVoucherPolicy::class);
        Gate::policy(GstEntry::class, GstEntryPolicy::class);
        Gate::policy(GstReturnPeriod::class, GstReturnPeriodPolicy::class);
        Gate::policy(PerformanceCycle::class, PerformanceCyclePolicy::class);
        Gate::policy(PerformanceReview::class, PerformanceReviewPolicy::class);
        Gate::policy(Vendor::class, VendorPolicy::class);
    }
}
