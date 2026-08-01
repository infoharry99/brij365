<?php

namespace App\Services\Builder360;

use App\Application\Scoring\Actions\ReadCurrentScores;
use App\Domain\Scoring\Support\LogicCenterPermissions;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\BookingPaymentSchedule;
use App\Models\BoqItem;
use App\Models\Branch;
use App\Models\CalendarEvent;
use App\Models\ChatConversation;
use App\Models\CollaborationMessage;
use App\Models\Company;
use App\Models\CollectionReceipt;
use App\Models\CommonAreaHandoverItem;
use App\Models\ComplianceObligation;
use App\Models\ConstructionMilestone;
use App\Models\ContractorBill;
use App\Models\ContractorMeasurement;
use App\Models\Customer;
use App\Models\DataImportBatch;
use App\Models\DailyProgressReport;
use App\Models\DocumentCategory;
use App\Models\Employee;
use App\Models\EmployeeAsset;
use App\Models\EmployeeConfirmationCase;
use App\Models\EmployeeExitInterview;
use App\Models\EmployeeLoan;
use App\Models\EmployeePolicyAcknowledgement;
use App\Models\EmployeeSeparationSettlement;
use App\Models\ErpModule;
use App\Models\ExpenseClaim;
use App\Models\FinancialVoucher;
use App\Models\HandoverSnag;
use App\Models\HrHelpdeskTicket;
use App\Models\Candidate;
use App\Models\Interview;
use App\Models\JobOffer;
use App\Models\JobOpening;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadQualification;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRegularizationRequest;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveEncashment;
use App\Models\LeaveProcessingRun;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\MaintenanceDue;
use App\Models\MarketingCampaign;
use App\Models\MailboxAccount;
use App\Models\MaintenanceWorkOrder;
use App\Models\ManagedDocument;
use App\Models\Partner;
use App\Models\PaymentRequest;
use App\Models\CommissionRule;
use App\Models\CommissionRun;
use App\Models\EmployeeTaxDocument;
use App\Models\PayrollBankTransferBatch;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\SalaryStructure;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\PossessionHandover;
use App\Models\ProjectUnit;
use App\Models\ProjectApproval;
use App\Models\ProjectTeamAssignment;
use App\Models\ProspectInquiry;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\Project;
use App\Models\ServiceTicket;
use App\Models\Role;
use App\Models\ReraRegistration;
use App\Models\ReportPin;
use App\Models\ReportSchedule;
use App\Models\SiteVisit;
use App\Models\SocietyFormation;
use App\Models\StockItem;
use App\Models\SystemSetting;
use App\Models\ScoringRule;
use App\Models\UnitPriceVersion;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WorkTask;
use App\Services\Buyer\BuyerPortalSummaryService;
use App\Services\Collaboration\ChatAccessService;
use App\Services\Finance\FinanceDashboardService;
use App\Services\Crm\LeadQualityScoreService;
use App\Services\Notifications\NotificationSummaryService;
use App\Services\Partner\PartnerDashboardService;
use App\Services\Partner\PartnerScopeService;
use App\Services\Security\CompanyScopeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class Builder360Bootstrap
{
    public function __construct(private readonly ReadCurrentScores $readCurrentScores) {}

    private ?int $selectedProjectId = null;
    /**
     * @var array<string, mixed>
     */
    private array $dashboardPeriod = [];

    /**
     * @var array<int, array<int, string>>
     */
    private array $dashboardRouteCache = [];

    /**
     * @return array<string, mixed>
     */
    public function forUser(?User $user, ?User $actor = null, ?string $selectedRoleSlug = null, ?int $selectedProjectId = null, ?array $dashboardPeriod = null): array
    {
        $actor ??= $user;
        $this->selectedProjectId = $this->visibleProjectForUser($user, $selectedProjectId)?->id;
        $this->dashboardPeriod = $this->normalizeDashboardPeriod($dashboardPeriod);
        $dashboard = $this->dashboardForUser($user);

        return [
            'schema' => 'builder360.bootstrap.v1',
            'app' => [
                'name' => config('app.name'),
                'environment' => config('app.env'),
                'database' => config('database.default'),
            ],
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->slug,
                'company' => $user->company?->code,
            ] : null,
            'active_role_context' => $this->activeRoleContext($actor, $user, $selectedRoleSlug),
            'active_project_context' => $this->activeProjectContext($user),
            'active_dashboard_period' => $this->dashboardPeriod,
            'selected_project_id' => $this->selectedProjectId,
            'roles' => $this->roleRows($actor),
            'modules' => $this->moduleGroups($user, $actor),
            'companies' => $this->companyRows($user),
            'projects' => $this->projectRows($user),
            'project_master_options' => $this->projectMasterOptions($user),
            'inventory_pricing_options' => $this->inventoryPricingOptions($user),
            'crm_leads' => $this->crmLeadRows($user),
            'crm_lead_metrics' => $this->crmLeadMetrics($user),
            'crm_lead_create_options' => $this->crmLeadCreateOptions($user),
            'crm_qualification_options' => $this->crmQualificationOptions($user),
            'prospect_inquiry_options' => $this->prospectInquiryOptions($user),
            'crm_import_options' => $this->crmImportOptions($user),
            'crm_site_visit_options' => $this->crmSiteVisitOptions($user),
            'crm_booking_options' => $this->crmBookingOptions($user),
            'sales_booking_options' => $this->salesBookingOptions($user),
            'collection_metrics' => $this->collectionMetrics($user),
            'collection_receipt_options' => $this->collectionReceiptOptions($user),
            'finance_dashboard' => $this->financeDashboard($user),
            'finance_voucher_options' => $this->financeVoucherOptions($user),
            'finance_payment_request_options' => $this->financePaymentRequestOptions($user),
            'approval_inbox_options' => $this->approvalInboxOptions($user),
            'governance_report_options' => $this->governanceReportOptions($user),
            'audit_trail_options' => $this->auditTrailOptions($user),
            'admin_governance_options' => $this->adminGovernanceOptions($user),
            'auth_security_options' => $this->authSecurityOptions($user),
            'account_profile_options' => $this->accountProfileOptions($user, $actor, $selectedRoleSlug),
            'hr_dashboard_options' => $this->hrDashboardOptions($user),
            'hr_helpdesk_options' => $this->hrHelpdeskOptions($user),
            'hr_self_service_options' => $this->hrSelfServiceOptions($user),
            'hr_leave_options' => $this->hrLeaveOptions($user),
            'hr_attendance_options' => $this->hrAttendanceOptions($user),
            'hr_recruitment_options' => $this->hrRecruitmentOptions($user),
            'hr_performance_options' => $this->hrPerformanceOptions($user),
            'hr_lifecycle_options' => $this->hrLifecycleOptions($user),
            'hr_payroll_options' => $this->hrPayrollOptions($user),
            'hr_compliance_options' => $this->hrComplianceOptions($user),
            'hr_operations_options' => $this->hrOperationsOptions($user),
            'hr_employee_options' => $this->hrEmployeeOptions($user),
            'hr_report_options' => $this->hrReportOptions($user),
            'possession_handover_options' => $this->possessionHandoverOptions($user),
            'after_sales_options' => $this->afterSalesOptions($user),
            'maintenance_society_options' => $this->maintenanceSocietyOptions($user),
            'legal_compliance_options' => $this->legalComplianceOptions($user),
            'document_management_options' => $this->documentManagementOptions($user),
            'construction_site_options' => $this->constructionSiteOptions($user),
            'construction_boq_options' => $this->constructionBoqOptions($user),
            'marketing_metrics' => $this->marketingMetrics($user),
            'sales_funnel_metrics' => $this->salesFunnelMetrics($user),
            'sales_performance_metrics' => $this->salesPerformanceMetrics($user),
            'partner_pipeline' => $this->partnerPipeline($user),
            'partner_portal' => $this->partnerPortal($user),
            'buyer_portal' => $this->buyerPortal($user),
            'mobile_journey_options' => $this->mobileJourneyOptions($user),
            'notifications' => $this->notifications($user),
            'collaboration_task_options' => $this->collaborationTaskOptions($user),
            'collaboration_calendar_options' => $this->collaborationCalendarOptions($user),
            'chat_connect_options' => $this->chatConnectOptions($user),
            'collaboration_mailbox_options' => null,
            'company_mailbox_options' => $this->companyMailboxOptions($user),
            'dashboard' => $dashboard,
            'role_dashboard' => $this->constrainPreviewDashboardRoutes(
                $this->roleDashboardForUser($user, $dashboard),
                $actor,
                $user,
            ),
            'management_dashboard' => $dashboard,
            'sales_dashboard' => $dashboard,
            'construction_dashboard' => $dashboard,
            'finance_dashboard_summary' => $this->financeRoleDashboard($user),
            'hr_dashboard_summary' => $this->hrRoleDashboard($user),
            'payroll_dashboard' => $this->payrollRoleDashboard($user),
            'recruitment_dashboard' => $this->recruitmentRoleDashboard($user),
            'audit_dashboard' => $this->auditRoleDashboard($user),
            'compliance_dashboard' => $this->complianceRoleDashboard($user),
            'system_admin_dashboard' => $this->systemAdminRoleDashboard($user),
            'buyer_dashboard' => $this->buyerRoleDashboard($user),
            'employee_dashboard' => $this->employeeRoleDashboard($user),
            'partner_dashboard' => $this->partnerRoleDashboard($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forRoleContext(User $actor, ?string $roleSlug = null, ?int $selectedProjectId = null, ?array $dashboardPeriod = null): array
    {
        $effectiveUser = $this->effectiveUserForRoleContext($actor, $roleSlug);

        return $this->forUser($effectiveUser, $actor, $effectiveUser->role?->slug, $selectedProjectId, $dashboardPeriod);
    }

    /**
     * Read the server-rendered dashboard contract without assembling unrelated module payloads.
     *
     * @param  array<string, mixed>|null  $dashboardPeriod
     * @return array<string, mixed>
     */
    public function dashboardForRoleContext(
        User $actor,
        ?string $roleSlug = null,
        ?int $selectedProjectId = null,
        ?array $dashboardPeriod = null,
    ): array {
        $effectiveUser = $this->effectiveUserForRoleContext($actor, $roleSlug);
        $this->selectedProjectId = $this->visibleProjectForUser($effectiveUser, $selectedProjectId)?->id;
        $this->dashboardPeriod = $this->normalizeDashboardPeriod($dashboardPeriod);
        $dashboard = $this->dashboardForUser($effectiveUser);

        return [
            'active_role_context' => $this->activeRoleContext($actor, $effectiveUser, $effectiveUser->role?->slug),
            'active_project_context' => $this->activeProjectContext($effectiveUser),
            'active_dashboard_period' => $this->dashboardPeriod,
            'role_dashboard' => $this->constrainPreviewDashboardRoutes(
                $this->roleDashboardForUser($effectiveUser, $dashboard),
                $actor,
                $effectiveUser,
            ),
            'buyer_portal' => $effectiveUser->role?->slug === 'buyer' ? ['available' => true] : null,
            'partner_portal' => in_array($effectiveUser->role?->slug, ['channel_partner', 'executive_partner_broker'], true)
                ? ['available' => true]
                : null,
        ];
    }

    /** @return array<string, mixed> */
    public function identityContextForRoleContext(User $actor, ?string $roleSlug = null, ?int $selectedProjectId = null): array
    {
        $effectiveUser = $this->effectiveUserForRoleContext($actor, $roleSlug);
        $this->selectedProjectId = $this->visibleProjectForUser($effectiveUser, $selectedProjectId)?->id;

        return [
            'active_role_context' => $this->activeRoleContext($actor, $effectiveUser, $effectiveUser->role?->slug),
            'active_project_context' => $this->activeProjectContext($effectiveUser),
        ];
    }

    public function projectIsVisibleForRoleContext(User $actor, ?string $roleSlug, int $projectId): bool
    {
        $effectiveUser = $this->effectiveUserForRoleContext($actor, $roleSlug);

        return $this->visibleProjectForUser($effectiveUser, $projectId) !== null;
    }

    private function effectiveUserForRoleContext(User $actor, ?string $roleSlug): User
    {
        $roleSlug = trim((string) ($roleSlug ?: $actor->role?->slug));

        if ($roleSlug === '') {
            return $actor->loadMissing(['role', 'company']);
        }

        $role = Role::query()
            ->where('slug', $roleSlug)
            ->where('is_active', true)
            ->firstOrFail();

        if (! $this->canSwitchRoleContext($actor, $role)) {
            throw new AuthorizationException('You are not authorized to switch to this Builder360 role context.');
        }

        if ((int) $actor->role_id === (int) $role->id) {
            return $actor->loadMissing(['role', 'company']);
        }

        $effectiveUser = User::query()
            ->with(['role', 'company'])
            ->where('role_id', $role->id)
            ->where('status', 'active')
            ->when($actor->company_id, fn (Builder $query): Builder => $query->orderByRaw('case when company_id = ? then 0 else 1 end', [$actor->company_id]))
            ->orderBy('id')
            ->first();

        if (! $effectiveUser) {
            throw new AuthorizationException('No active user is available for this Builder360 role context.');
        }

        return $effectiveUser;
    }

    private function canSwitchRoleContext(User $actor, Role $role): bool
    {
        if ((int) $actor->role_id === (int) $role->id) {
            return true;
        }

        $actorSlug = $actor->role?->slug;

        return $actor->hasPermission('*') || in_array($actorSlug, ['director', 'system_admin'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function activeRoleContext(?User $actor, ?User $effectiveUser, ?string $selectedRoleSlug): array
    {
        $role = $effectiveUser?->role;

        return [
            'mode' => $actor && $effectiveUser && (int) $actor->id !== (int) $effectiveUser->id ? 'selected_role_preview' : 'authenticated_role',
            'actor_user_id' => $actor?->id,
            'actor_role_slug' => $actor?->role?->slug,
            'effective_user_id' => $effectiveUser?->id,
            'role_slug' => $role?->slug ?? $selectedRoleSlug,
            'role_name' => $role?->name,
            'is_impersonated_preview' => $actor && $effectiveUser ? (int) $actor->id !== (int) $effectiveUser->id : false,
            'can_switch_roles' => $actor ? ($actor->hasPermission('*') || in_array($actor->role?->slug, ['director', 'system_admin'], true)) : false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activeProjectContext(?User $user): array
    {
        $project = $this->selectedProjectId
            ? Project::query()->whereKey($this->selectedProjectId)->first(['id', 'company_id', 'code', 'name', 'status'])
            : null;

        return [
            'mode' => $project ? 'selected_project' : 'all_projects',
            'project_id' => $project?->id,
            'project_code' => $project?->code,
            'project_name' => $project?->name,
            'company_id' => $project?->company_id,
            'can_switch_projects' => $user !== null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $period
     * @return array<string, mixed>
     */
    private function normalizeDashboardPeriod(?array $period): array
    {
        $key = is_string($period['key'] ?? null) ? $period['key'] : (is_string($period['period_key'] ?? null) ? $period['period_key'] : 'current_month');
        $now = now();

        $range = match ($key) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'Today'],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek(), 'This Week'],
            'previous_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth(), 'Previous Month'],
            'current_quarter' => [
                $now->copy()->month(((int) floor(($now->month - 1) / 3) * 3) + 1)->startOfMonth()->startOfDay(),
                $now->copy()->month(((int) floor(($now->month - 1) / 3) * 3) + 3)->endOfMonth()->endOfDay(),
                'Current Quarter',
            ],
            'current_financial_year' => [
                $now->copy()->month >= 4 ? $now->copy()->month(4)->startOfMonth()->startOfDay() : $now->copy()->subYear()->month(4)->startOfMonth()->startOfDay(),
                $now->copy()->month >= 4 ? $now->copy()->addYear()->month(3)->endOfMonth()->endOfDay() : $now->copy()->month(3)->endOfMonth()->endOfDay(),
                'Current Financial Year',
            ],
            'custom' => $this->customDashboardPeriodRange($period),
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'Current Month'],
        };

        if ($key !== 'custom' && ! in_array($key, array_column($this->dashboardPeriodOptions(), 'key'), true)) {
            $key = 'current_month';
        }

        [$from, $to, $label] = $range;

        return [
            'key' => $key,
            'label' => $label,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'options' => $this->dashboardPeriodOptions(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $period
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon, 2: string}
     */
    private function customDashboardPeriodRange(?array $period): array
    {
        $from = is_string($period['date_from'] ?? null) ? \Illuminate\Support\Carbon::parse($period['date_from'])->startOfDay() : now()->startOfMonth();
        $to = is_string($period['date_to'] ?? null) ? \Illuminate\Support\Carbon::parse($period['date_to'])->endOfDay() : now()->endOfMonth();

        if ($to->lt($from)) {
            $to = $from->copy()->endOfDay();
        }

        return [$from, $to, 'Custom Range'];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function dashboardPeriodOptions(): array
    {
        return [
            ['key' => 'today', 'label' => 'Today'],
            ['key' => 'this_week', 'label' => 'This Week'],
            ['key' => 'current_month', 'label' => 'Current Month'],
            ['key' => 'previous_month', 'label' => 'Previous Month'],
            ['key' => 'current_quarter', 'label' => 'Current Quarter'],
            ['key' => 'current_financial_year', 'label' => 'Current Financial Year'],
            ['key' => 'custom', 'label' => 'Custom Range'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function financeRoleDashboard(?User $user): ?array
    {
        $dashboard = $this->financeDashboard($user);

        return $dashboard ? [
            'source' => 'laravel',
            'title' => 'Finance Dashboard',
            'cash_position' => $dashboard['cash_position'] ?? [],
            'period_summary' => $dashboard['period_summary'] ?? [],
            'approvals' => $dashboard['approvals'] ?? [],
        ] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function hrRoleDashboard(?User $user): ?array
    {
        $options = $this->hrDashboardOptions($user);

        return $options ? [
            'source' => 'laravel',
            'title' => 'HR Dashboard',
            'summary' => $options['summary'] ?? [],
            'department_headcount' => $options['department_headcount'] ?? [],
        ] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function payrollRoleDashboard(?User $user): ?array
    {
        $options = $this->hrPayrollOptions($user);

        return $options ? [
            'source' => 'laravel',
            'title' => 'Payroll Dashboard',
            'summary' => $options['summary'] ?? [],
            'payroll_runs' => $options['payroll_runs'] ?? [],
            'bank_batches' => $options['bank_batches'] ?? [],
            'tax_documents' => $options['tax_documents'] ?? [],
            'commission_runs' => $options['commission_runs'] ?? [],
        ] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function recruitmentRoleDashboard(?User $user): ?array
    {
        $options = $this->hrRecruitmentOptions($user);

        return $options ? [
            'source' => 'laravel',
            'title' => 'Recruitment Dashboard',
            'summary' => $options['summary'] ?? [],
            'candidates' => $options['candidates'] ?? [],
            'interviews' => $options['interviews'] ?? [],
            'offers' => $options['offers'] ?? [],
            'sources' => $options['sources'] ?? [],
        ] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function auditRoleDashboard(?User $user): ?array
    {
        $options = $this->auditTrailOptions($user);
        $reports = $this->governanceReportOptions($user);

        return $options || $reports ? [
            'source' => 'laravel',
            'title' => 'Audit & Governance Dashboard',
            'audit' => $options ?? [],
            'reports' => $reports ?? [],
        ] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function complianceRoleDashboard(?User $user): ?array
    {
        $legal = $this->legalComplianceOptions($user);
        $hr = $this->hrComplianceOptions($user);
        $documents = $this->documentManagementOptions($user);

        return $legal || $hr || $documents ? [
            'source' => 'laravel',
            'title' => 'Compliance Dashboard',
            'legal' => $legal ?? [],
            'hr' => $hr ?? [],
            'documents' => $documents ?? [],
        ] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function systemAdminRoleDashboard(?User $user): ?array
    {
        $admin = $this->adminGovernanceOptions($user);
        $auth = $this->authSecurityOptions($user);

        return $admin || $auth ? [
            'source' => 'laravel',
            'title' => 'System Administration Dashboard',
            'admin' => $admin ?? [],
            'auth' => $auth ?? [],
            'notifications' => $this->notifications($user),
        ] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buyerRoleDashboard(?User $user): ?array
    {
        $portal = $this->buyerPortal($user);

        return $portal ? [
            'source' => 'laravel',
            'title' => 'Buyer Dashboard',
            'portal' => $portal,
        ] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function employeeRoleDashboard(?User $user): ?array
    {
        $selfService = $this->hrSelfServiceOptions($user);

        return $selfService ? [
            'source' => 'laravel',
            'title' => 'Employee Dashboard',
            'self_service' => $selfService,
        ] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function partnerRoleDashboard(?User $user): ?array
    {
        $portal = $this->partnerPortal($user);

        return $portal ? [
            'source' => 'laravel',
            'title' => $user?->role?->slug === 'executive_partner_broker' ? 'Executive Partner Broker Dashboard' : 'Channel Partner Dashboard',
            'portal' => $portal,
        ] : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function roleDashboardForUser(?User $user, array $managementDashboard): array
    {
        return match ($user?->role?->slug) {
            'sales_head' => $this->normalizedManagementRoleDashboard($user, 'Sales Dashboard', 'Sales pipeline, bookings, collections and team focus.', 'leads', 'Open Sales CRM', $managementDashboard),
            'construction_head' => $this->normalizedManagementRoleDashboard($user, 'Construction Dashboard', 'Project progress, construction alerts and approval focus.', 'planning', 'Open Construction Planning', $managementDashboard),
            'finance_head' => $this->normalizedFinanceRoleDashboard($user),
            'hr_manager' => $this->normalizedHrRoleDashboard($user),
            'payroll' => $this->normalizedPayrollRoleDashboard($user),
            'recruiter' => $this->normalizedRecruitmentRoleDashboard($user),
            'auditor' => $this->normalizedAuditRoleDashboard($user),
            'compliance' => $this->normalizedComplianceRoleDashboard($user),
            'system_admin' => $this->normalizedSystemAdminRoleDashboard($user),
            'employee' => $this->normalizedEmployeeRoleDashboard($user),
            'buyer' => $this->normalizedBuyerRoleDashboard($user),
            'channel_partner' => $this->normalizedPartnerRoleDashboard($user, 'Channel Partner Dashboard'),
            'executive_partner_broker' => $this->normalizedPartnerRoleDashboard($user, 'Executive Partner Broker Dashboard'),
            'director' => $this->normalizedManagementRoleDashboard($user, 'Management Dashboard', 'Executive control across sales, projects, finance, HR and customer operations.', 'reports', 'Open Reports', $managementDashboard),
            default => $this->normalizedManagementRoleDashboard($user, 'Role dashboard not ready', 'This role is active, but a dedicated dashboard is not ready yet.', 'reports', 'Open Reports', $managementDashboard),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $stats
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    private function normalizedRoleDashboard(?User $user, string $title, string $subtitle, string $primaryRoute, string $primaryLabel, array $stats, array $sections, array $charts = [], array $alerts = [], array $tables = [], array $quickActions = []): array
    {
        $safePrimaryRoute = $this->permittedDashboardRoute($user, $primaryRoute) ?? 'dashboard';
        $normalizedStats = array_values(array_map(fn (array $stat): array => $this->normalizeDashboardItem($stat, $safePrimaryRoute, $user, 'stat'), $stats));
        $normalizedSections = array_values(array_map(fn (array $section): array => $this->normalizeDashboardSection($section, $safePrimaryRoute, $user), $sections));
        $normalizedCharts = $this->normalizeDashboardCharts($charts ?: $this->defaultDashboardCharts($normalizedStats, $normalizedSections), $safePrimaryRoute, $user);
        $normalizedAlerts = $this->normalizeDashboardRows($alerts ?: $this->defaultDashboardAlerts($normalizedSections), $safePrimaryRoute, $safePrimaryRoute, $user);
        $normalizedTables = $this->normalizeDashboardTables($tables ?: $this->defaultDashboardTables($normalizedSections), $safePrimaryRoute, $user);
        $normalizedQuickActions = $this->normalizeDashboardQuickActions($quickActions ?: $this->defaultDashboardQuickActions($user, $safePrimaryRoute, $primaryLabel), $safePrimaryRoute, $user);

        return [
            'role_slug' => $user?->role?->slug,
            'role_name' => $user?->role?->name,
            'title' => $title,
            'subtitle' => $subtitle,
            'context' => $this->dashboardContext($user),
            'period' => $this->dashboardPeriod,
            'primary_route' => $safePrimaryRoute,
            'primary_label' => $primaryLabel,
            'stats' => $normalizedStats,
            'charts' => $normalizedCharts,
            'alerts' => $normalizedAlerts,
            'tables' => $normalizedTables,
            'quick_actions' => $normalizedQuickActions,
            'sections' => $normalizedSections,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDashboardRows(array $rows, string $sectionRoute, string $primaryRoute, ?User $user = null): array
    {
        return array_values(array_map(fn (array $row): array => $this->normalizeDashboardItem($row, $sectionRoute ?: $primaryRoute, $user, 'row'), $rows));
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardContext(?User $user): array
    {
        $project = $this->selectedProjectId
            ? Project::query()->whereKey($this->selectedProjectId)->first(['id', 'company_id'])
            : null;

        return [
            'company_id' => $user?->company_id,
            'project_id' => $project?->id,
            'period_key' => $this->dashboardPeriod['key'] ?? 'current_month',
            'date_from' => $this->dashboardPeriod['date_from'] ?? null,
            'date_to' => $this->dashboardPeriod['date_to'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeDashboardItem(array $item, string $fallbackRoute, ?User $user, string $kind = 'item'): array
    {
        $route = $this->permittedDashboardRoute($user, $this->dashboardText($item['route'] ?? $fallbackRoute));
        $routeFilter = is_array($item['route_filter'] ?? null) ? array_filter($item['route_filter'], fn ($value): bool => $value !== null && $value !== '') : [];

        return [
            'key' => $this->dashboardText($item['key'] ?? str($item['label'] ?? $kind)->slug('_')->toString()),
            'label' => $this->dashboardText($item['label'] ?? 'Metric'),
            'value' => $this->dashboardText($item['value'] ?? '—'),
            'sub' => $this->dashboardText($item['sub'] ?? ''),
            'icon' => $this->dashboardText($item['icon'] ?? ($kind === 'row' ? 'chevR' : 'bar')),
            'tone' => $this->dashboardText($item['tone'] ?? 'b-blue'),
            'value_type' => $this->dashboardText($item['value_type'] ?? $this->dashboardValueType($item['value'] ?? null)),
            'source' => $this->dashboardText($item['source'] ?? 'Business records'),
            'route' => $route,
            'route_filter' => $route ? $routeFilter : [],
            'empty' => $this->dashboardText($item['empty'] ?? 'No records are available for your selected view.'),
            'is_actionable' => (bool) ($route && ($item['is_actionable'] ?? true)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeDashboardSection(array $section, string $primaryRoute, ?User $user): array
    {
        $sectionRoute = $this->permittedDashboardRoute($user, $this->dashboardText($section['route'] ?? $primaryRoute));
        $modeRows = collect(is_array($section['mode_rows'] ?? null) ? $section['mode_rows'] : [])
            ->mapWithKeys(fn ($rows, $mode): array => [
                $this->dashboardText($mode) => $this->normalizeDashboardRows(is_array($rows) ? $rows : [], $sectionRoute ?: $primaryRoute, $primaryRoute, $user),
            ])
            ->all();
        $viewOptions = array_values(array_filter(array_map(fn ($option): string => $this->dashboardText($option), is_array($section['view_options'] ?? null) ? $section['view_options'] : []), fn (string $option): bool => $option !== '—'));

        if ($viewOptions && ! $modeRows) {
            $viewOptions = [];
        }

        return [
            'key' => $this->dashboardText($section['key'] ?? str($section['title'] ?? 'records')->slug('_')->toString()),
            'title' => $this->dashboardText($section['title'] ?? 'Records'),
            'sub' => $this->dashboardText($section['sub'] ?? ''),
            'empty' => $this->dashboardText($section['empty'] ?? 'No records are available for your selected view.'),
            'route' => $sectionRoute,
            'route_filter' => $sectionRoute && is_array($section['route_filter'] ?? null) ? array_filter($section['route_filter'], fn ($value): bool => $value !== null && $value !== '') : [],
            'view_mode' => $this->dashboardText($section['view_mode'] ?? ''),
            'view_options' => $viewOptions,
            'rows' => $this->normalizeDashboardRows(is_array($section['rows'] ?? null) ? $section['rows'] : [], $sectionRoute ?: $primaryRoute, $primaryRoute, $user),
            'mode_rows' => $modeRows,
            'is_actionable' => (bool) $sectionRoute,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $charts
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDashboardCharts(array $charts, string $primaryRoute, ?User $user): array
    {
        return array_values(array_map(function (array $chart) use ($primaryRoute, $user): array {
            $route = $this->permittedDashboardRoute($user, $this->dashboardText($chart['route'] ?? $primaryRoute));

            return [
                'key' => $this->dashboardText($chart['key'] ?? str($chart['title'] ?? 'chart')->slug('_')->toString()),
                'title' => $this->dashboardText($chart['title'] ?? 'Chart'),
                'sub' => $this->dashboardText($chart['sub'] ?? ''),
                'type' => $this->dashboardText($chart['type'] ?? 'bar'),
                'route' => $route,
                'route_filter' => $route && is_array($chart['route_filter'] ?? null) ? array_filter($chart['route_filter'], fn ($value): bool => $value !== null && $value !== '') : [],
                'empty' => $this->dashboardText($chart['empty'] ?? 'No chart data is available for your selected view.'),
                'rows' => $this->normalizeDashboardRows(is_array($chart['rows'] ?? null) ? $chart['rows'] : [], $route ?: $primaryRoute, $primaryRoute, $user),
                'is_actionable' => (bool) $route,
            ];
        }, $charts));
    }

    /**
     * @param  array<int, array<string, mixed>>  $tables
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDashboardTables(array $tables, string $primaryRoute, ?User $user): array
    {
        return array_values(array_map(function (array $table) use ($primaryRoute, $user): array {
            $route = $this->permittedDashboardRoute($user, $this->dashboardText($table['route'] ?? $primaryRoute));

            return [
                'key' => $this->dashboardText($table['key'] ?? str($table['title'] ?? 'table')->slug('_')->toString()),
                'title' => $this->dashboardText($table['title'] ?? 'Table'),
                'sub' => $this->dashboardText($table['sub'] ?? ''),
                'route' => $route,
                'route_filter' => $route && is_array($table['route_filter'] ?? null) ? array_filter($table['route_filter'], fn ($value): bool => $value !== null && $value !== '') : [],
                'empty' => $this->dashboardText($table['empty'] ?? 'No table records are available for your selected view.'),
                'columns' => array_values(array_map(fn ($column): string => $this->dashboardText($column), is_array($table['columns'] ?? null) ? $table['columns'] : ['Item', 'Status', 'Value'])),
                'rows' => $this->normalizeDashboardRows(is_array($table['rows'] ?? null) ? $table['rows'] : [], $route ?: $primaryRoute, $primaryRoute, $user),
                'is_actionable' => (bool) $route,
            ];
        }, $tables));
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDashboardQuickActions(array $actions, string $primaryRoute, ?User $user): array
    {
        return array_values(array_filter(array_map(function (array $action) use ($primaryRoute, $user): array {
            $route = $this->permittedDashboardRoute($user, $this->dashboardText($action['route'] ?? $primaryRoute));

            return [
                'key' => $this->dashboardText($action['key'] ?? str($action['label'] ?? 'action')->slug('_')->toString()),
                'label' => $this->dashboardText($action['label'] ?? 'Open'),
                'icon' => $this->dashboardText($action['icon'] ?? 'chevR'),
                'route' => $route,
                'route_filter' => $route && is_array($action['route_filter'] ?? null) ? array_filter($action['route_filter'], fn ($value): bool => $value !== null && $value !== '') : [],
                'is_actionable' => (bool) $route,
            ];
        }, $actions), fn (array $action): bool => (bool) $action['route']));
    }

    /**
     * @param  array<int, array<string, mixed>>  $stats
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function defaultDashboardCharts(array $stats, array $sections): array
    {
        $statRows = array_slice(array_filter($stats, fn (array $stat): bool => is_numeric(str_replace([',', '₹'], '', (string) ($stat['value'] ?? '')))), 0, 6);
        $section = $sections[0] ?? null;

        return array_values(array_filter([
            $statRows ? ['title' => 'Key Metrics', 'sub' => 'Current dashboard indicators', 'type' => 'bar', 'rows' => $statRows] : null,
            is_array($section) ? ['title' => $section['title'] ?? 'Performance', 'sub' => $section['sub'] ?? '', 'type' => 'bar', 'route' => $section['route'] ?? null, 'rows' => array_slice(is_array($section['rows'] ?? null) ? $section['rows'] : [], 0, 6)] : null,
        ]));
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function defaultDashboardAlerts(array $sections): array
    {
        $rows = collect($sections)
            ->flatMap(fn (array $section) => is_array($section['rows'] ?? null) ? $section['rows'] : [])
            ->filter(fn (array $row): bool => in_array($row['tone'] ?? '', ['b-red', 'b-orange'], true))
            ->take(6)
            ->values()
            ->all();

        return $rows ?: [[
            'label' => 'No urgent items',
            'sub' => 'Nothing requires immediate action in this view.',
            'value' => 'Clear',
            'tone' => 'b-green',
            'route' => null,
            'is_actionable' => false,
        ]];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function defaultDashboardTables(array $sections): array
    {
        return array_values(array_map(fn (array $section): array => [
            'title' => $section['title'] ?? 'Records',
            'sub' => $section['sub'] ?? '',
            'route' => $section['route'] ?? null,
            'columns' => ['Item', 'Details', 'Value'],
            'rows' => is_array($section['rows'] ?? null) ? $section['rows'] : [],
            'empty' => $section['empty'] ?? 'No records are available for your selected view.',
        ], array_slice($sections, 0, 2)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultDashboardQuickActions(?User $user, string $primaryRoute, string $primaryLabel): array
    {
        return match ($user?->role?->slug) {
            'sales_head' => [
                ['label' => 'Add Lead', 'icon' => 'plus', 'route' => 'leads'],
                ['label' => 'Open Funnel', 'icon' => 'funnel', 'route' => 'funnel'],
                ['label' => 'Open Site Visits', 'icon' => 'calendar', 'route' => 'sitevisits'],
            ],
            'construction_head' => [
                ['label' => 'Open Planning', 'icon' => 'calendar', 'route' => 'planning'],
                ['label' => 'Daily Progress', 'icon' => 'check', 'route' => 'progress'],
                ['label' => 'Procurement', 'icon' => 'package', 'route' => 'procurement'],
            ],
            'finance_head' => [
                ['label' => 'Open Finance', 'icon' => 'wallet', 'route' => 'finance'],
                ['label' => 'Open Collections', 'icon' => 'rupee', 'route' => 'collections'],
                ['label' => 'Open Approvals', 'icon' => 'check', 'route' => 'approvals'],
            ],
            'hr_manager' => [
                ['label' => 'Open HR', 'icon' => 'users', 'route' => 'hr'],
                ['label' => 'Open Leave', 'icon' => 'calendar', 'route' => 'hr', 'route_filter' => ['tab' => 'leave']],
                ['label' => 'Open Helpdesk', 'icon' => 'chat', 'route' => 'hr', 'route_filter' => ['tab' => 'helpdesk']],
            ],
            'payroll' => [
                ['label' => 'Open Payroll', 'icon' => 'wallet', 'route' => 'payroll'],
                ['label' => 'Bank Batches', 'icon' => 'bank', 'route' => 'payroll'],
                ['label' => 'Tax Docs', 'icon' => 'file', 'route' => 'payroll'],
            ],
            'recruiter' => [
                ['label' => 'Add Candidate', 'icon' => 'plus', 'route' => 'recruitment'],
                ['label' => 'Schedule Interview', 'icon' => 'calendar', 'route' => 'recruitment'],
                ['label' => 'Open Offers', 'icon' => 'file', 'route' => 'recruitment'],
            ],
            'auditor' => [
                ['label' => 'Activity History', 'icon' => 'shield', 'route' => 'audit'],
                ['label' => 'Open Reports', 'icon' => 'bar', 'route' => 'reports'],
            ],
            'compliance' => [
                ['label' => 'Legal/RERA', 'icon' => 'shield', 'route' => 'legal'],
                ['label' => 'Documents', 'icon' => 'folder', 'route' => 'documents'],
                ['label' => 'Compliance Reports', 'icon' => 'bar', 'route' => 'reports'],
            ],
            'system_admin' => [
                ['label' => 'User Management', 'icon' => 'users', 'route' => 'admin', 'route_filter' => ['tab' => 'users']],
                ['label' => 'Data Imports', 'icon' => 'download', 'route' => 'admin', 'route_filter' => ['tab' => 'data-imports']],
                ['label' => 'Settings', 'icon' => 'settings', 'route' => 'admin', 'route_filter' => ['tab' => 'settings']],
            ],
            'employee' => [
                ['label' => 'Self-Service', 'icon' => 'users', 'route' => 'ess'],
                ['label' => 'My Tasks', 'icon' => 'tasks', 'route' => 'tasks'],
                ['label' => 'My Calendar', 'icon' => 'calendar', 'route' => 'calendar'],
            ],
            'buyer' => [
                ['label' => 'My Booking', 'icon' => 'home', 'route' => 'buyer', 'route_filter' => ['tab' => 'bookings']],
                ['label' => 'Payments', 'icon' => 'wallet', 'route' => 'buyer', 'route_filter' => ['tab' => 'payments']],
                ['label' => 'Service Requests', 'icon' => 'chat', 'route' => 'buyer', 'route_filter' => ['tab' => 'tickets']],
            ],
            'channel_partner', 'executive_partner_broker' => [
                ['label' => 'Open Leads', 'icon' => 'users', 'route' => 'partner', 'route_filter' => ['tab' => 'leads']],
                ['label' => 'Site Visits', 'icon' => 'calendar', 'route' => 'partner', 'route_filter' => ['tab' => 'site-visits']],
                ['label' => 'Commission', 'icon' => 'wallet', 'route' => 'partner', 'route_filter' => ['tab' => 'commission']],
            ],
            default => [
                ['label' => $primaryLabel, 'icon' => 'chevR', 'route' => $primaryRoute],
                ['label' => 'Open Projects', 'icon' => 'grid', 'route' => 'projects'],
                ['label' => 'Open Approvals', 'icon' => 'check', 'route' => 'approvals'],
            ],
        };
    }

    private function dashboardValueType(mixed $value): string
    {
        $text = (string) $value;

        return str_contains($text, '₹') || str_contains(strtolower($text), 'inr') ? 'money'
            : (str_contains($text, '%') ? 'percent' : (is_numeric(str_replace(',', '', $text)) ? 'number' : 'text'));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function dashboardProjectHealthValue(array $row): string
    {
        if (! isset($row['health']) || ! is_numeric($row['health'])) {
            return 'Not calculated';
        }

        return number_format((float) $row['health'], 2).'%';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function dashboardProjectHealthTone(array $row): string
    {
        if (! isset($row['health']) || ! is_numeric($row['health'])) {
            return 'b-slate';
        }

        $score = (float) $row['health'];

        return $score >= 80 ? 'b-green' : ($score >= 60 ? 'b-orange' : 'b-red');
    }

    private function permittedDashboardRoute(?User $user, mixed $route): ?string
    {
        $route = $this->dashboardText($route ?: '');
        if ($route === '' || $route === '—') {
            return null;
        }

        $baseRoute = str($route)->before('?')->toString();
        if (! $user) {
            return null;
        }

        $allowed = $this->dashboardRouteCache[$user->id] ??= collect($this->moduleGroups($user, $user))
            ->flatMap(fn (array $group) => collect($group['items'] ?? [])->pluck('route'))
            ->filter(fn ($moduleRoute): bool => is_string($moduleRoute) && $moduleRoute !== '')
            ->merge(['dashboard', 'notifications', 'profile'])
            ->unique()
            ->values()
            ->all();

        return in_array($baseRoute, $allowed, true) ? $route : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedManagementRoleDashboard(?User $user, string $title, string $subtitle, string $primaryRoute, string $primaryLabel, array $dashboard): array
    {
        $kpis = is_array($dashboard['kpis'] ?? null) ? $dashboard['kpis'] : [];

        return $this->normalizedRoleDashboard($user, $title, $subtitle, $primaryRoute, $primaryLabel, [
            ['label' => 'Projects', 'value' => $kpis['projects'] ?? count(is_array($dashboard['projects'] ?? null) ? $dashboard['projects'] : []), 'sub' => 'available to you', 'icon' => 'grid', 'tone' => 'b-blue', 'route' => 'projects'],
            ['label' => 'Total Units', 'value' => $kpis['totalUnits'] ?? 0, 'sub' => ($kpis['available'] ?? 0).' available', 'icon' => 'building', 'tone' => 'b-blue', 'route' => 'inventory'],
            ['label' => 'Bookings', 'value' => $kpis['bookings'] ?? 0, 'sub' => ($kpis['siteVisits'] ?? 0).' site visits', 'icon' => 'home', 'tone' => 'b-green', 'route' => 'sales'],
            ['label' => 'Collections', 'value' => $this->dashboardMoney(($kpis['collection'] ?? 0) * 10000000), 'sub' => 'receipts and dues', 'icon' => 'wallet', 'tone' => 'b-violet', 'route' => 'collections'],
            ['label' => 'Expenses', 'value' => $this->dashboardMoney(($kpis['expenses'] ?? 0) * 10000000), 'sub' => 'approved spend', 'icon' => 'rupee', 'tone' => 'b-orange', 'route' => 'finance'],
            ['label' => 'Blended ROI', 'value' => $kpis['roi'] ?? 0, 'sub' => 'portfolio average', 'icon' => 'trend', 'tone' => 'b-violet', 'route' => 'cost'],
            ['label' => 'Leads', 'value' => $kpis['leads'] ?? 0, 'sub' => ($kpis['verified'] ?? 0).' qualified', 'icon' => 'users', 'tone' => 'b-blue', 'route' => 'leads'],
            ['label' => 'Pending Approvals', 'value' => count(is_array($dashboard['approvals'] ?? null) ? $dashboard['approvals'] : []), 'sub' => 'items awaiting action', 'icon' => 'check', 'tone' => 'b-orange', 'route' => 'approvals'],
        ], [
            [
                'title' => 'Project Health Scorecard',
                'sub' => 'Project progress, sales, collection and ROI.',
                'route' => 'projects',
                'view_mode' => 'health',
                'view_options' => ['Health', 'ROI'],
                'mode_rows' => [
                    'Health' => array_map(fn ($row): array => [
                        'label' => $row['name'] ?? $row['project'] ?? 'Project',
                        'sub' => trim('Construction '.($row['progress'] ?? 0).'% · Collection '.($row['collection_percent'] ?? 0).'% · '.$this->dashboardText($row['status'] ?? ''), ' ·'),
                        'value' => $this->dashboardProjectHealthValue($row),
                        'tone' => $this->dashboardProjectHealthTone($row),
                        'route' => 'projects',
                        'route_filter' => ['project_id' => $row['db_id'] ?? $row['id'] ?? null],
                    ], is_array($dashboard['projects'] ?? null) ? $dashboard['projects'] : []),
                    'ROI' => array_map(fn ($row): array => [
                        'label' => $row['name'] ?? $row['project'] ?? 'Project',
                        'sub' => trim('Budget '.($row['budget_used_percent'] ?? 0).'% · Sales '.($row['sales_percent'] ?? 0).'% · Collection '.($row['collection_percent'] ?? 0).'%', ' ·'),
                        'value' => ($row['roi'] ?? $row['target_roi_percent'] ?? 0).'%',
                        'tone' => ($row['roi'] ?? $row['target_roi_percent'] ?? 0) >= 20 ? 'b-green' : (($row['roi'] ?? $row['target_roi_percent'] ?? 0) > 0 ? 'b-orange' : 'b-slate'),
                        'route' => 'cost',
                        'route_filter' => ['project_id' => $row['db_id'] ?? $row['id'] ?? null],
                    ], is_array($dashboard['projects'] ?? null) ? $dashboard['projects'] : []),
                ],
                'rows' => array_map(fn ($row): array => [
                    'label' => $row['name'] ?? $row['project'] ?? 'Project',
                    'sub' => trim('Construction '.($row['progress'] ?? 0).'% · Collection '.($row['collection_percent'] ?? 0).'% · '.$this->dashboardText($row['status'] ?? ''), ' ·'),
                    'value' => $this->dashboardProjectHealthValue($row),
                    'tone' => $this->dashboardProjectHealthTone($row),
                    'route' => 'projects',
                    'route_filter' => ['project_id' => $row['db_id'] ?? $row['id'] ?? null],
                ], is_array($dashboard['projects'] ?? null) ? $dashboard['projects'] : []),
            ],
            [
                'title' => 'Alerts & Approvals',
                'sub' => 'management actions',
                'route' => 'approvals',
                'rows' => array_merge(
                    array_map(fn ($row): array => [
                        'label' => $row['title'] ?? $row['label'] ?? 'Approval',
                        'sub' => $row['sub'] ?? $row['type'] ?? 'approval',
                        'value' => $row['status'] ?? 'Pending',
                        'tone' => 'b-orange',
                        'route' => 'approvals',
                    ], is_array($dashboard['approvals'] ?? null) ? $dashboard['approvals'] : []),
                    array_map(fn ($row): array => [
                        'label' => $row['title'] ?? $row['label'] ?? 'Alert',
                        'sub' => $row['sub'] ?? $row['type'] ?? 'alert',
                        'value' => $row['severity'] ?? 'Alert',
                        'tone' => 'b-red',
                        'route' => $row['route'] ?? 'reports',
                    ], is_array($dashboard['alerts'] ?? null) ? $dashboard['alerts'] : [])
                ),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedFinanceRoleDashboard(?User $user): array
    {
        $dashboard = $this->financeDashboard($user) ?? [];
        $cash = is_array($dashboard['cash_position'] ?? null) ? $dashboard['cash_position'] : [];
        $period = is_array($dashboard['period_summary'] ?? null) ? $dashboard['period_summary'] : [];
        $approvals = is_array($dashboard['approvals']['rows'] ?? null) ? $dashboard['approvals']['rows'] : (is_array($dashboard['approvals'] ?? null) ? $dashboard['approvals'] : []);

        return $this->normalizedRoleDashboard($user, 'Finance Dashboard', 'Cash position, receivables, payables, GST and approvals.', 'finance', 'Open Finance', [
            ['label' => 'Cash In', 'value' => $this->dashboardMoney($cash['cash_in'] ?? $period['cash_in'] ?? 0), 'sub' => 'current visible scope', 'icon' => 'wallet', 'tone' => 'b-green', 'route' => 'collections'],
            ['label' => 'Cash Out', 'value' => $this->dashboardMoney($cash['cash_out'] ?? $period['cash_out'] ?? 0), 'sub' => 'approved payments', 'icon' => 'wallet', 'tone' => 'b-orange', 'route' => 'finance', 'route_filter' => ['tab' => 'payments']],
            ['label' => 'Net Position', 'value' => $this->dashboardMoney($cash['net_position'] ?? $period['net_position'] ?? 0), 'sub' => 'cash control total', 'icon' => 'bar', 'tone' => 'b-blue', 'route' => 'finance'],
            ['label' => 'Approvals', 'value' => count($approvals), 'sub' => 'finance review queue', 'icon' => 'check', 'tone' => 'b-violet', 'route' => 'approvals'],
            ['label' => 'GST Controls', 'value' => is_array($dashboard['gst'] ?? null) ? 1 : 0, 'sub' => 'tax follow-up items', 'icon' => 'file', 'tone' => 'b-blue', 'route' => 'finance', 'route_filter' => ['tab' => 'gst']],
            ['label' => 'Receivables', 'value' => $this->dashboardMoney($dashboard['receivables']['total'] ?? $dashboard['receivables']['amount'] ?? 0), 'sub' => 'customer dues', 'icon' => 'rupee', 'tone' => 'b-orange', 'route' => 'collections'],
        ], [
            ['title' => 'Finance Approvals', 'sub' => 'pending/recent actions', 'rows' => array_map(fn ($row): array => [
                'label' => $row['title'] ?? $row['reference'] ?? 'Finance approval',
                'sub' => $row['sub'] ?? $row['type'] ?? $row['status'] ?? 'approval',
                'value' => $row['amount'] ?? $row['status'] ?? 'Review',
                'tone' => 'b-orange',
                'route' => 'approvals',
            ], $approvals)],
            ['title' => 'GST & Cash Controls', 'sub' => 'finance dashboard indicators', 'rows' => array_map(fn ($row): array => [
                'label' => $row['label'] ?? 'Finance indicator',
                'sub' => $row['sub'] ?? 'indicator',
                'value' => $row['value'] ?? 'Open',
                'tone' => 'b-blue',
            ], array_values(array_filter([
                is_array($dashboard['gst'] ?? null) ? ['label' => 'GST Summary', 'sub' => $dashboard['gst']['status'] ?? 'GST', 'value' => $dashboard['gst']['value'] ?? $dashboard['gst']['net_payable'] ?? 'Review'] : null,
                is_array($dashboard['receivables'] ?? null) ? ['label' => 'Receivables', 'sub' => 'open customer dues', 'value' => $dashboard['receivables']['total'] ?? $dashboard['receivables']['amount'] ?? 'Review'] : null,
                is_array($dashboard['payables'] ?? null) ? ['label' => 'Payables', 'sub' => 'vendor/payment dues', 'value' => $dashboard['payables']['total'] ?? $dashboard['payables']['amount'] ?? 'Review'] : null,
            ])))],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedHrRoleDashboard(?User $user): array
    {
        $options = $this->hrDashboardOptions($user) ?? [];
        $summary = is_array($options['summary'] ?? null) ? $options['summary'] : [];

        return $this->normalizedRoleDashboard($user, 'HR Dashboard', 'Employee operations, attendance, leave and HR approvals.', 'hr', 'Open HR', [
            ['label' => 'Employees', 'value' => $summary['employees'] ?? $summary['active_employees'] ?? 0, 'sub' => 'active workforce', 'icon' => 'users', 'tone' => 'b-blue', 'route' => 'hr', 'route_filter' => ['tab' => 'employees']],
            ['label' => 'Attendance', 'value' => $summary['attendance_percent'] ?? $summary['attendance'] ?? '—', 'sub' => 'current period', 'icon' => 'clock', 'tone' => 'b-green', 'route' => 'hr', 'route_filter' => ['tab' => 'attendance']],
            ['label' => 'Leave Requests', 'value' => $summary['leave_requests'] ?? $summary['open_leave_requests'] ?? 0, 'sub' => 'approval queue', 'icon' => 'calendar', 'tone' => 'b-orange', 'route' => 'hr', 'route_filter' => ['tab' => 'leave']],
            ['label' => 'HR Actions', 'value' => $summary['pending_approvals'] ?? 0, 'sub' => 'manager/HR review', 'icon' => 'check', 'tone' => 'b-violet', 'route' => 'approvals'],
            ['label' => 'Recruitment', 'value' => $summary['candidate_pipeline'] ?? 0, 'sub' => 'candidate pipeline', 'icon' => 'briefcase', 'tone' => 'b-blue', 'route' => 'hr', 'route_filter' => ['tab' => 'recruitment']],
            ['label' => 'Helpdesk', 'value' => $summary['open_helpdesk_tickets'] ?? 0, 'sub' => 'open employee tickets', 'icon' => 'chat', 'tone' => 'b-orange', 'route' => 'hr', 'route_filter' => ['tab' => 'helpdesk']],
        ], [
            ['title' => 'HR Work Queue', 'sub' => 'employee lifecycle actions', 'rows' => array_map(fn ($row): array => [
                'label' => $row['title'] ?? $row['employee_name'] ?? $row['name'] ?? 'HR action',
                'sub' => $row['status'] ?? $row['type'] ?? 'workflow',
                'value' => $row['due_on'] ?? $row['priority'] ?? 'Open',
                'tone' => 'b-orange',
                'route' => 'hr',
            ], is_array($options['work_queue'] ?? null) ? $options['work_queue'] : [])],
            ['title' => 'HR Alerts', 'sub' => 'policy and compliance indicators', 'rows' => array_map(fn ($row): array => [
                'label' => $row['title'] ?? $row['label'] ?? 'HR alert',
                'sub' => $row['sub'] ?? $row['status'] ?? 'alert',
                'value' => $row['value'] ?? $row['severity'] ?? 'Alert',
                'tone' => 'b-red',
            ], is_array($options['alerts'] ?? null) ? $options['alerts'] : [])],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedPayrollRoleDashboard(?User $user): array
    {
        $companyIds = $this->visibleCompanyIds($user);
        $scope = fn ($query) => is_array($companyIds) ? $query->whereIn('company_id', $companyIds) : $query;

        $runs = $this->applyDashboardPeriod($scope(PayrollRun::query()), 'created_at')->latest('id')->limit(6)->get();
        $bankBatches = $this->applyDashboardPeriod($scope(PayrollBankTransferBatch::query()), 'created_at')->latest('id')->limit(6)->get();
        $taxDocuments = $this->applyDashboardPeriod($scope(EmployeeTaxDocument::query()), 'created_at')->latest('id')->limit(6)->get();
        $commissionRuns = $this->applyDashboardPeriod($scope(CommissionRun::query()), 'created_at')->latest('id')->limit(6)->get();
        $salaryStructures = $scope(SalaryStructure::query())->count();

        return $this->normalizedRoleDashboard($user, 'Payroll Dashboard', 'Payroll runs, salary payouts, bank batches, commissions and tax documents.', 'payroll', 'Open Payroll', [
            ['label' => 'Payroll Runs', 'value' => $runs->count(), 'sub' => 'recent runs in scope', 'icon' => 'wallet', 'tone' => 'b-blue', 'route' => 'payroll'],
            ['label' => 'Net Payable', 'value' => $this->dashboardMoney($runs->sum('net_payable')), 'sub' => 'recent payroll total', 'icon' => 'wallet', 'tone' => 'b-green', 'route' => 'payroll'],
            ['label' => 'Salary Structures', 'value' => $salaryStructures, 'sub' => 'active pay structures', 'icon' => 'file', 'tone' => 'b-blue', 'route' => 'payroll'],
            ['label' => 'Bank Batches', 'value' => $bankBatches->count(), 'sub' => 'transfer batches', 'icon' => 'bank', 'tone' => 'b-violet', 'route' => 'payroll'],
            ['label' => 'Tax Docs', 'value' => $taxDocuments->count(), 'sub' => 'Form 16/tax documents', 'icon' => 'file', 'tone' => 'b-orange', 'route' => 'payroll'],
            ['label' => 'Commission Runs', 'value' => $commissionRuns->count(), 'sub' => 'sales incentive batches', 'icon' => 'trend', 'tone' => 'b-green', 'route' => 'payroll'],
        ], [
            ['title' => 'Recent Payroll Runs', 'sub' => 'run status and net payable', 'rows' => $runs->map(fn (PayrollRun $run): array => [
                'label' => $run->run_number,
                'sub' => trim(($run->period_month ?? '').'/'.($run->period_year ?? '').' · '.$run->status, ' ·/'),
                'value' => $this->dashboardMoney($run->net_payable),
                'tone' => $run->status === 'approved' ? 'b-green' : 'b-orange',
                'route' => 'payroll',
            ])->all()],
            ['title' => 'Bank, Tax & Commission Queue', 'sub' => 'finance release and statutory readiness', 'rows' => array_merge(
                $bankBatches->map(fn (PayrollBankTransferBatch $batch): array => ['label' => $batch->batch_number, 'sub' => $batch->bank_name ?: $batch->status, 'value' => $this->dashboardMoney($batch->control_total), 'tone' => 'b-violet'])->all(),
                $taxDocuments->map(fn (EmployeeTaxDocument $doc): array => ['label' => $doc->document_number, 'sub' => $doc->financial_year ?: $doc->document_type, 'value' => $doc->status, 'tone' => 'b-blue'])->all(),
                $commissionRuns->map(fn (CommissionRun $run): array => ['label' => $run->run_number, 'sub' => trim(($run->period_month ?? '').'/'.($run->period_year ?? '').' · '.$run->status, ' ·/'), 'value' => $this->dashboardMoney($run->commission_total), 'tone' => 'b-green'])->all()
            )],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedRecruitmentRoleDashboard(?User $user): array
    {
        $options = $this->hrRecruitmentOptions($user) ?? [];
        $summary = is_array($options['summary'] ?? null) ? $options['summary'] : [];
        $companyIds = $this->visibleCompanyIds($user);
        $scope = fn ($query) => is_array($companyIds) ? $query->whereIn('company_id', $companyIds) : $query;

        $candidates = $this->applyDashboardPeriod($scope(Candidate::query()), 'created_at')->latest('id')->limit(5)->get();
        $interviews = $this->applyDashboardPeriod($scope(Interview::query()->with('candidate:id,name')), 'scheduled_at')->latest('id')->limit(5)->get();
        $offers = $this->applyDashboardPeriod($scope(JobOffer::query()->with('candidate:id,name')), 'created_at')->latest('id')->limit(5)->get();
        $sourceRows = is_array($options['candidate_sources'] ?? null) ? $options['candidate_sources'] : (is_array($options['sources'] ?? null) ? $options['sources'] : []);

        return $this->normalizedRoleDashboard($user, 'Recruitment Dashboard', 'Hiring pipeline, candidate sources, interviews and offers.', 'recruitment', 'Open Recruitment', [
            ['label' => 'Open Positions', 'value' => $summary['open_positions'] ?? 0, 'sub' => 'approved requisitions', 'icon' => 'briefcase', 'tone' => 'b-blue', 'route' => 'recruitment'],
            ['label' => 'Candidates', 'value' => $summary['candidates'] ?? $candidates->count(), 'sub' => 'candidate master', 'icon' => 'users', 'tone' => 'b-green', 'route' => 'recruitment'],
            ['label' => 'Interviews', 'value' => $summary['interviews'] ?? $interviews->count(), 'sub' => 'scheduled/recent', 'icon' => 'calendar', 'tone' => 'b-orange', 'route' => 'recruitment'],
            ['label' => 'Offers Pending', 'value' => $summary['offers_pending'] ?? $offers->where('status', '!=', 'accepted')->count(), 'sub' => 'offer workflow', 'icon' => 'file', 'tone' => 'b-violet', 'route' => 'recruitment'],
            ['label' => 'Sources', 'value' => count($sourceRows), 'sub' => 'active hiring channels', 'icon' => 'funnel', 'tone' => 'b-blue', 'route' => 'recruitment'],
        ], [
            ['title' => 'Job Openings', 'sub' => 'from recruitment workspace', 'rows' => array_map(fn ($row): array => [
                'label' => $row['title'] ?? $row['opening_code'] ?? 'Job opening',
                'sub' => trim(($row['department'] ?? '').' · '.($row['designation'] ?? '').' · '.($row['status'] ?? ''), ' ·'),
                'value' => $row['positions'] ?? $row['status'] ?? 'Open',
                'tone' => 'b-blue',
            ], is_array($options['job_openings'] ?? null) ? $options['job_openings'] : [])],
            ['title' => 'Candidates, Interviews & Offers', 'sub' => 'recent hiring activity', 'rows' => array_merge(
                $candidates->map(fn (Candidate $candidate): array => ['label' => $candidate->name, 'sub' => trim(($candidate->source ?? '').' · '.($candidate->stage ?? '').' · '.($candidate->status ?? ''), ' ·'), 'value' => $candidate->experience_years ? $candidate->experience_years.' yrs' : $candidate->status, 'tone' => 'b-green'])->all(),
                $interviews->map(fn (Interview $interview): array => ['label' => $interview->candidate?->name ?? $interview->interview_code, 'sub' => trim(($interview->round_name ?? '').' · '.($interview->mode ?? '').' · '.optional($interview->scheduled_at)->format('d M Y H:i'), ' ·'), 'value' => $interview->status, 'tone' => 'b-orange'])->all(),
                $offers->map(fn (JobOffer $offer): array => ['label' => $offer->candidate?->name ?? $offer->offer_number, 'sub' => $offer->offer_number ?: 'offer', 'value' => $this->dashboardMoney($offer->offered_ctc), 'tone' => 'b-violet'])->all()
            )],
            ['title' => 'Source Performance', 'sub' => 'candidate-source mix', 'rows' => array_map(fn ($row): array => [
                'label' => $row['source'] ?? $row['label'] ?? 'Source',
                'sub' => $row['status'] ?? 'candidate source',
                'value' => $row['count'] ?? $row['value'] ?? 0,
                'tone' => 'b-slate',
                'route' => 'recruitment',
            ], $sourceRows)],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedAuditRoleDashboard(?User $user): array
    {
        $audit = $this->auditTrailOptions($user) ?? [];
        $reports = $this->governanceReportOptions($user) ?? [];
        $events = $this->applyDashboardPeriod(AuditEvent::query(), 'created_at')->latest('id')->limit(8)->get();

        return $this->normalizedRoleDashboard($user, 'Audit & Governance Dashboard', 'Activity records, report controls, schedules and review evidence.', 'reports', 'Open Reports', [
            ['label' => 'Recent Events', 'value' => $events->count(), 'sub' => 'latest activity rows', 'icon' => 'shield', 'tone' => 'b-blue', 'route' => 'audit'],
            ['label' => 'Report Types', 'value' => count(is_array($reports['supported_reports'] ?? null) ? $reports['supported_reports'] : []), 'sub' => 'available reports', 'icon' => 'bar', 'tone' => 'b-green', 'route' => 'reports'],
            ['label' => 'Pinned Reports', 'value' => count(is_array($reports['pinned_reports'] ?? null) ? $reports['pinned_reports'] : []), 'sub' => 'quick review shortcuts', 'icon' => 'pin', 'tone' => 'b-violet', 'route' => 'reports'],
            ['label' => 'Export Formats', 'value' => count(is_array($audit['supported_exports'] ?? null) ? $audit['supported_exports'] : []), 'sub' => 'available exports', 'icon' => 'download', 'tone' => 'b-orange', 'route' => 'reports'],
        ], [
            ['title' => 'Recent Audit Events', 'sub' => 'latest system evidence', 'rows' => $events->map(fn (AuditEvent $event): array => [
                'label' => $event->event_type ?: $event->action ?: 'Audit event',
                'sub' => trim(($event->auditable_type ?? '').' #'.($event->auditable_id ?? '').' · '.$event->created_at?->format('d M Y H:i'), ' ·#'),
                'value' => $event->action ?: 'Logged',
                'tone' => 'b-blue',
                'route' => 'audit',
            ])->all()],
            ['title' => 'Reports & Schedules', 'sub' => 'report setup and schedules', 'rows' => array_merge(
                array_map(fn ($row): array => ['label' => $row['label'] ?? $row['name'] ?? $row['key'] ?? 'Report', 'sub' => $row['description'] ?? $row['scope'] ?? 'supported report', 'value' => $row['format'] ?? 'Report', 'tone' => 'b-green'], is_array($reports['supported_reports'] ?? null) ? $reports['supported_reports'] : []),
                array_map(fn ($row): array => ['label' => $row['title'] ?? $row['label'] ?? 'Pinned report', 'sub' => $row['frequency'] ?? $row['status'] ?? 'pinned', 'value' => $row['status'] ?? 'Pinned', 'tone' => 'b-violet'], is_array($reports['pinned_reports'] ?? null) ? $reports['pinned_reports'] : []),
                array_map(fn ($row): array => ['label' => $row['title'] ?? $row['label'] ?? 'Scheduled report', 'sub' => $row['frequency'] ?? $row['next_run_at'] ?? 'scheduled', 'value' => $row['status'] ?? 'Scheduled', 'tone' => 'b-orange'], is_array($reports['scheduled_reports'] ?? null) ? $reports['scheduled_reports'] : [])
            )],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedComplianceRoleDashboard(?User $user): array
    {
        $companyIds = $this->visibleCompanyIds($user);
        $scope = fn ($query) => is_array($companyIds) ? $query->whereIn('company_id', $companyIds) : $query;

        $obligations = $this->applyDashboardPeriod($scope(ComplianceObligation::query()), 'due_on')->latest('id')->limit(6)->get();
        $rera = $this->applyDashboardPeriod($scope(ReraRegistration::query()), 'expires_on')->latest('id')->limit(6)->get();
        $documents = $this->applyDashboardPeriod($scope(ManagedDocument::query()), 'expires_on')->whereNotNull('expires_on')->orderBy('expires_on')->limit(6)->get();
        $hr = $this->hrComplianceOptions($user) ?? [];

        return $this->normalizedRoleDashboard($user, 'Compliance Dashboard', 'HR statutory settings, RERA/legal reminders, obligations and expiring documents.', 'legal', 'Open Compliance', [
            ['label' => 'Obligations', 'value' => $obligations->count(), 'sub' => 'legal/statutory queue', 'icon' => 'shield', 'tone' => 'b-blue', 'route' => 'legal'],
            ['label' => 'RERA Records', 'value' => $rera->count(), 'sub' => 'registration controls', 'icon' => 'file', 'tone' => 'b-green', 'route' => 'legal'],
            ['label' => 'Expiring Docs', 'value' => $documents->count(), 'sub' => 'expiry-monitored documents', 'icon' => 'calendar', 'tone' => 'b-orange', 'route' => 'documents'],
            ['label' => 'HR Settings', 'value' => count(is_array($hr['settings'] ?? null) ? $hr['settings'] : []), 'sub' => 'statutory settings', 'icon' => 'settings', 'tone' => 'b-violet', 'route' => 'legal'],
            ['label' => 'Compliance Reports', 'value' => count(is_array($hr['reports'] ?? null) ? $hr['reports'] : []), 'sub' => 'review reports', 'icon' => 'bar', 'tone' => 'b-green', 'route' => 'reports'],
        ], [
            ['title' => 'Legal & Statutory Obligations', 'sub' => 'due dates and RERA controls', 'rows' => array_merge(
                $obligations->map(fn (ComplianceObligation $row): array => ['label' => $row->title, 'sub' => trim(($row->compliance_type ?? '').' · '.optional($row->due_on)->format('d M Y'), ' ·'), 'value' => $row->status, 'tone' => $row->status === 'completed' ? 'b-green' : 'b-orange'])->all(),
                $rera->map(fn (ReraRegistration $row): array => ['label' => $row->registration_number, 'sub' => trim(($row->authority_name ?? '').' · '.optional($row->expires_on)->format('d M Y'), ' ·'), 'value' => $row->status, 'tone' => 'b-blue'])->all()
            )],
            ['title' => 'Documents & HR Compliance Settings', 'sub' => 'expiry and statutory settings', 'rows' => array_merge(
                $documents->map(fn (ManagedDocument $row): array => ['label' => $row->title, 'sub' => optional($row->expires_on)->format('d M Y') ?: $row->status, 'value' => $row->status, 'tone' => 'b-orange'])->all(),
                array_map(fn ($row): array => ['label' => $row['label'] ?? $row['name'] ?? $row['key'] ?? 'HR compliance setting', 'sub' => $row['group'] ?? $row['setting_group'] ?? 'setting', 'value' => $row['status'] ?? $row['value'] ?? 'Set up', 'tone' => 'b-violet'], is_array($hr['settings'] ?? null) ? $hr['settings'] : [])
            )],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedSystemAdminRoleDashboard(?User $user): array
    {
        $admin = $this->adminGovernanceOptions($user) ?? [];
        $users = is_array($admin['users'] ?? null) ? $admin['users'] : [];
        $roles = is_array($admin['roles'] ?? null) ? $admin['roles'] : [];
        $settings = is_array($admin['settings'] ?? null) ? $admin['settings'] : [];
        $imports = is_array($admin['data_imports'] ?? null) ? $admin['data_imports'] : [];
        $approvals = is_array($admin['approval_chains'] ?? null) ? $admin['approval_chains'] : [];

        return $this->normalizedRoleDashboard($user, 'System Administration Dashboard', 'Users, roles, settings, imports, approvals and system management.', 'admin', 'Open Admin & Masters', [
            ['label' => 'Users', 'value' => count($users), 'sub' => 'active users', 'icon' => 'users', 'tone' => 'b-blue', 'route' => 'admin', 'route_filter' => ['tab' => 'users']],
            ['label' => 'Roles', 'value' => count($roles), 'sub' => 'access profiles', 'icon' => 'shield', 'tone' => 'b-green', 'route' => 'admin', 'route_filter' => ['tab' => 'users']],
            ['label' => 'Settings', 'value' => count($settings), 'sub' => 'master settings records', 'icon' => 'settings', 'tone' => 'b-violet', 'route' => 'admin', 'route_filter' => ['tab' => 'settings']],
            ['label' => 'Imports', 'value' => count($imports), 'sub' => 'data import batches', 'icon' => 'download', 'tone' => 'b-blue', 'route' => 'admin', 'route_filter' => ['tab' => 'data-imports']],
            ['label' => 'Approval Chains', 'value' => count($approvals), 'sub' => 'approval setup', 'icon' => 'check', 'tone' => 'b-orange', 'route' => 'admin', 'route_filter' => ['tab' => 'approvals']],
            ['label' => 'Notifications', 'value' => count(is_array($admin['notifications'] ?? null) ? $admin['notifications'] : []), 'sub' => 'communication settings', 'icon' => 'bell', 'tone' => 'b-green', 'route' => 'notifications'],
        ], [
            ['title' => 'Users & Roles', 'sub' => 'role-safe administration overview', 'rows' => array_merge(
                array_map(fn ($row): array => [
                    'label' => $row['name'] ?? $row['email'] ?? 'User',
                    'sub' => trim(($row['role']['name'] ?? $row['role_name'] ?? $row['role_slug'] ?? 'role').' · '.($row['company']['code'] ?? $row['company_code'] ?? 'company').' · '.($row['status'] ?? ''), ' ·'),
                    'value' => $row['status'] ?? 'Active',
                    'tone' => ($row['status'] ?? null) === 'active' ? 'b-green' : 'b-slate',
                ], $users),
                array_map(fn ($row): array => [
                    'label' => $row['name'] ?? $row['slug'] ?? 'Role',
                    'sub' => $row['scope_level'] ?? $row['scope'] ?? 'role',
                    'value' => $row['permissions_count'] ?? count(is_array($row['permissions'] ?? null) ? $row['permissions'] : []),
                    'tone' => 'b-blue',
                ], $roles)
            )],
            ['title' => 'Settings, Imports & Approvals', 'sub' => 'settings and approval setup', 'rows' => array_merge(
                array_map(fn ($row): array => ['label' => $row['label'] ?? $row['name'] ?? $row['key'] ?? 'Setting', 'sub' => $row['group'] ?? $row['setting_group'] ?? 'setting', 'value' => $row['status'] ?? $row['value'] ?? 'Set up', 'tone' => 'b-violet'], $settings),
                array_map(fn ($row): array => ['label' => $row['label'] ?? $row['name'] ?? $row['import_type'] ?? 'Data import', 'sub' => $row['status'] ?? 'import', 'value' => $row['last_run_at'] ?? $row['status'] ?? 'Ready', 'tone' => 'b-orange'], $imports),
                array_map(fn ($row): array => ['label' => $row['label'] ?? $row['name'] ?? $row['workflow'] ?? 'Approval chain', 'sub' => $row['scope'] ?? $row['status'] ?? 'approval', 'value' => $row['status'] ?? 'Active', 'tone' => 'b-green'], $approvals)
            )],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedEmployeeRoleDashboard(?User $user): array
    {
        $self = $this->hrSelfServiceOptions($user) ?? [];
        $summary = is_array($self['summary'] ?? null) ? $self['summary'] : [];

        return $this->normalizedRoleDashboard($user, 'Employee Dashboard', 'Self-service attendance, leave, payslip and personal work queue.', 'ess', 'Open Self-Service', [
            ['label' => 'Attendance', 'value' => $summary['attendance_percent'] ?? '—', 'sub' => 'attendance percentage', 'icon' => 'clock', 'tone' => 'b-green', 'route' => 'ess', 'route_filter' => ['tab' => 'attendance']],
            ['label' => 'Leave Balance', 'value' => $summary['leave_available_days'] ?? 0, 'sub' => 'available leave days', 'icon' => 'calendar', 'tone' => 'b-blue', 'route' => 'ess', 'route_filter' => ['tab' => 'leave']],
            ['label' => 'Open Requests', 'value' => $summary['open_requests'] ?? 0, 'sub' => 'employee requests', 'icon' => 'file', 'tone' => 'b-orange', 'route' => 'ess', 'route_filter' => ['tab' => 'requests']],
            ['label' => 'Latest Payslip', 'value' => $this->dashboardMoney($summary['latest_payslip_net_payable'] ?? 0), 'sub' => $summary['latest_payslip_period'] ?? $summary['latest_payslip_status'] ?? 'payroll', 'icon' => 'wallet', 'tone' => 'b-violet', 'route' => 'ess', 'route_filter' => ['tab' => 'payroll']],
            ['label' => 'My Tasks', 'value' => $user ? WorkTask::query()->where('assigned_to_user_id', $user->id)->whereNotIn('status', ['completed', 'cancelled', 'archived'])->count() : 0, 'sub' => 'open assigned tasks', 'icon' => 'tasks', 'tone' => 'b-blue', 'route' => 'tasks'],
            ['label' => 'My Calendar', 'value' => $user ? CalendarEvent::query()->where('organizer_user_id', $user->id)->whereDate('starts_at', '>=', now()->toDateString())->count() : 0, 'sub' => 'upcoming events', 'icon' => 'calendar', 'tone' => 'b-green', 'route' => 'calendar'],
        ], [
            ['title' => 'Recent Attendance', 'sub' => 'employee attendance records', 'rows' => array_map(fn ($row): array => [
                'label' => $row['date'] ?? $row['attendance_date'] ?? 'Attendance',
                'sub' => $row['shift'] ?? $row['check_in'] ?? 'attendance',
                'value' => $row['status'] ?? 'Marked',
                'tone' => 'b-green',
            ], is_array($self['recent_attendance'] ?? null) ? $self['recent_attendance'] : [])],
            ['title' => 'Leave Types & Payroll Summary', 'sub' => 'self-service availability', 'rows' => array_map(fn ($row): array => [
                'label' => $row['name'] ?? $row['leave_type'] ?? 'Leave type',
                'sub' => $row['code'] ?? $row['policy'] ?? 'leave',
                'value' => $row['available_days'] ?? $row['balance'] ?? $row['status'] ?? 'Available',
                'tone' => 'b-blue',
            ], is_array($self['leave_types'] ?? null) ? $self['leave_types'] : [])],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedBuyerRoleDashboard(?User $user): array
    {
        $portal = $this->buyerPortal($user) ?? [];
        $bookings = is_array($portal['bookings'] ?? null) ? $portal['bookings'] : [];
        $payments = is_array($portal['payment_requests'] ?? null) ? $portal['payment_requests'] : (is_array($portal['payment_schedules'] ?? null) ? $portal['payment_schedules'] : []);
        $receipts = is_array($portal['receipts'] ?? null) ? $portal['receipts'] : [];
        $tickets = is_array($portal['service_tickets'] ?? null) ? $portal['service_tickets'] : (is_array($portal['tickets'] ?? null) ? $portal['tickets'] : []);

        return $this->normalizedRoleDashboard($user, 'Buyer Dashboard', 'Bookings, payment requests, receipts and service tickets.', 'buyer', 'Open Buyer Portal', [
            ['label' => 'Bookings', 'value' => count($bookings), 'sub' => 'buyer-visible units', 'icon' => 'home', 'tone' => 'b-blue', 'route' => 'buyer', 'route_filter' => ['tab' => 'bookings']],
            ['label' => 'Payments', 'value' => count($payments), 'sub' => 'payment schedule/request rows', 'icon' => 'wallet', 'tone' => 'b-orange', 'route' => 'buyer', 'route_filter' => ['tab' => 'payments']],
            ['label' => 'Receipts', 'value' => count($receipts), 'sub' => 'posted receipts', 'icon' => 'file', 'tone' => 'b-green', 'route' => 'buyer', 'route_filter' => ['tab' => 'receipts']],
            ['label' => 'Tickets', 'value' => count($tickets), 'sub' => 'service requests', 'icon' => 'chat', 'tone' => 'b-violet', 'route' => 'buyer', 'route_filter' => ['tab' => 'tickets']],
            ['label' => 'Documents', 'value' => count(is_array($portal['documents'] ?? null) ? $portal['documents'] : []), 'sub' => 'buyer document records', 'icon' => 'folder', 'tone' => 'b-blue', 'route' => 'buyer', 'route_filter' => ['tab' => 'documents']],
        ], [
            ['title' => 'My Bookings', 'sub' => 'your booked units', 'rows' => array_map(fn ($row): array => [
                'label' => $row['project'] ?? $row['project_name'] ?? $row['unit'] ?? $row['unit_code'] ?? 'Booking',
                'sub' => $row['booking_number'] ?? $row['status'] ?? 'booking',
                'value' => $row['status'] ?? 'Booked',
                'tone' => 'b-blue',
            ], $bookings)],
            ['title' => 'Payments, Receipts & Service', 'sub' => 'buyer follow-up queue', 'rows' => array_merge(
                array_map(fn ($row): array => ['label' => $row['milestone'] ?? $row['label'] ?? 'Payment', 'sub' => $row['due_date'] ?? $row['status'] ?? 'payment', 'value' => $row['amount'] ?? $row['status'] ?? 'Due', 'tone' => 'b-orange'], $payments),
                array_map(fn ($row): array => ['label' => $row['receipt_number'] ?? $row['label'] ?? 'Receipt', 'sub' => $row['date'] ?? $row['status'] ?? 'receipt', 'value' => $row['amount'] ?? $row['status'] ?? 'Posted', 'tone' => 'b-green'], $receipts),
                array_map(fn ($row): array => ['label' => $row['ticket_number'] ?? $row['subject'] ?? 'Service ticket', 'sub' => $row['status'] ?? 'ticket', 'value' => $row['status'] ?? 'Open', 'tone' => 'b-violet'], $tickets)
            )],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedPartnerRoleDashboard(?User $user, string $title): array
    {
        $portal = $this->partnerPortal($user) ?? [];
        $leads = is_array($portal['my_leads'] ?? null) ? $portal['my_leads'] : [];
        $visits = is_array($portal['site_visits'] ?? null) ? $portal['site_visits'] : [];
        $bookings = is_array($portal['bookings'] ?? null) ? $portal['bookings'] : [];
        $collections = is_array($portal['collections_follow_up'] ?? null) ? $portal['collections_follow_up'] : [];
        $commission = is_array($portal['commission_summary'] ?? null) ? $portal['commission_summary'] : [];
        $commissionItems = is_array($commission['items'] ?? null) ? $commission['items'] : [];

        return $this->normalizedRoleDashboard($user, $title, 'Partner-facing leads, visits, bookings, collections and commissions.', 'partner', 'Open Partner Portal', [
            ['label' => 'My Leads', 'value' => count($leads), 'sub' => 'partner-visible lead rows', 'icon' => 'users', 'tone' => 'b-blue', 'route' => 'partner', 'route_filter' => ['tab' => 'leads']],
            ['label' => 'Site Visits', 'value' => count($visits), 'sub' => 'visit follow-up', 'icon' => 'calendar', 'tone' => 'b-orange', 'route' => 'partner', 'route_filter' => ['tab' => 'site-visits']],
            ['label' => 'Bookings', 'value' => count($bookings), 'sub' => 'partner bookings', 'icon' => 'home', 'tone' => 'b-green', 'route' => 'partner', 'route_filter' => ['tab' => 'bookings']],
            ['label' => 'Collections', 'value' => count($collections), 'sub' => 'collection follow-up', 'icon' => 'rupee', 'tone' => 'b-orange', 'route' => 'partner', 'route_filter' => ['tab' => 'collections']],
            ['label' => 'Commission', 'value' => $this->dashboardMoney($commission['approved_amount'] ?? $commission['paid_amount'] ?? 0), 'sub' => 'approved/paid commission', 'icon' => 'wallet', 'tone' => 'b-violet', 'route' => 'partner', 'route_filter' => ['tab' => 'commission']],
            ['label' => 'Documents', 'value' => count(is_array($portal['documents'] ?? null) ? $portal['documents'] : []), 'sub' => 'partner documents', 'icon' => 'folder', 'tone' => 'b-blue', 'route' => 'documents'],
        ], [
            ['title' => 'My Leads & Site Visits', 'sub' => 'partner CRM activity', 'rows' => array_merge(
                array_map(fn ($row): array => ['label' => $row['name'] ?? $row['lead_name'] ?? 'Lead', 'sub' => $row['project'] ?? $row['stage'] ?? $row['status'] ?? 'lead', 'value' => $row['status'] ?? $row['stage'] ?? 'Lead', 'tone' => 'b-blue'], $leads),
                array_map(fn ($row): array => ['label' => $row['lead_name'] ?? $row['customer'] ?? 'Site visit', 'sub' => $row['visit_at'] ?? $row['scheduled_at'] ?? $row['status'] ?? 'visit', 'value' => $row['status'] ?? 'Visit', 'tone' => 'b-orange'], $visits)
            )],
            ['title' => 'Bookings, Collections & Commission', 'sub' => 'commercial follow-up', 'rows' => array_merge(
                array_map(fn ($row): array => ['label' => $row['booking_number'] ?? $row['customer'] ?? 'Booking', 'sub' => $row['project'] ?? $row['unit'] ?? $row['status'] ?? 'booking', 'value' => $row['status'] ?? 'Booked', 'tone' => 'b-green'], $bookings),
                array_map(fn ($row): array => ['label' => $row['customer'] ?? $row['label'] ?? 'Collection', 'sub' => $row['due_date'] ?? $row['status'] ?? 'collection', 'value' => $row['amount'] ?? $row['status'] ?? 'Due', 'tone' => 'b-orange'], $collections),
                array_map(fn ($row): array => ['label' => $row['label'] ?? $row['booking_number'] ?? 'Commission', 'sub' => $row['status'] ?? 'commission', 'value' => $row['amount'] ?? $row['commission_amount'] ?? 'Commission', 'tone' => 'b-violet'], $commissionItems)
            )],
        ]);
    }

    private function dashboardMoney(mixed $amount): string
    {
        if (! is_numeric($amount)) {
            return $this->dashboardText($amount);
        }

        return $this->currency((float) $amount);
    }

    private function dashboardText(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d M Y');
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            foreach (['name', 'label', 'title', 'code', 'slug', 'status', 'value'] as $key) {
                if (isset($value[$key]) && is_scalar($value[$key])) {
                    return (string) $value[$key];
                }
            }
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '—';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function roleRows(?User $user): array
    {
        $query = Role::query()
            ->where('is_active', true)
            ->orderBy('id');

        if (! $this->canViewRoleCatalogue($user)) {
            $query->whereKey($user?->role_id ?? 0);
        }

        return $query
            ->get(['id', 'slug', 'name', 'scope_level', 'permissions'])
            ->map(fn (Role $role) => [
                'slug' => $role->slug,
                'name' => $role->name,
                'scope_level' => $role->scope_level,
                'permissions' => $role->permissions ?? [],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function prospectInquiryOptions(?User $user): ?array
    {
        if (
            ! $user
            || $this->isPartnerPortalUser($user)
            || $this->isBuyerPortalUser($user)
            || (
                ! $user->hasPermission('*')
                && ! $user->hasPermission('crm.view')
                && ! $user->hasPermission('crm.manage')
                && ! $user->hasPermission('reports.view')
            )
        ) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);
        $projectIds = $this->visibleProjectIds($user);

        $projects = Project::query()
            ->with('company:id,code,name')
            ->withCount([
                'units as total_units_count',
                'units as available_units_count' => fn (Builder $query): Builder => $query->where('status', 'available'),
            ])
            ->withMin('units as starting_price', 'total_price')
            ->when(is_array($companyIds), fn (Builder $query): Builder => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query): Builder => $query->whereIn('id', $projectIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('code')
            ->limit(24)
            ->get(['id', 'company_id', 'code', 'name', 'city', 'state', 'project_type', 'status'])
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'code' => $project->code,
                'name' => $project->name,
                'company_code' => $project->company?->code,
                'city' => $project->city,
                'state' => $project->state,
                'project_type' => $project->project_type,
                'available_units' => (int) ($project->available_units_count ?? 0),
                'total_units' => (int) ($project->total_units_count ?? 0),
                'starting_price' => $project->starting_price !== null ? (float) $project->starting_price : null,
                'label' => $project->code.' · '.$project->name,
            ])
            ->values()
            ->all();

        $inquiryQuery = ProspectInquiry::query()
            ->when(is_array($companyIds), fn (Builder $query): Builder => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query): Builder => $query->whereIn('project_id', $projectIds ?: [0]));

        return [
            'source' => 'business-records',
            'store_url' => route('prospect-inquiries.store', [], false),
            'projects' => $projects,
            'metrics' => [
                'captured_30d' => (clone $inquiryQuery)->where('created_at', '>=', now()->subDays(30))->count(),
                'open' => (clone $inquiryQuery)->whereIn('status', ['new', 'assigned'])->count(),
                'converted_30d' => (clone $inquiryQuery)->whereNotNull('converted_at')->where('converted_at', '>=', now()->subDays(30))->count(),
                'duplicates_30d' => (clone $inquiryQuery)->where('status', 'duplicate')->where('created_at', '>=', now()->subDays(30))->count(),
            ],
            'channels' => [
                ['value' => 'website', 'label' => 'Website'],
                ['value' => 'landing_page', 'label' => 'Landing Page'],
                ['value' => 'mobile_app', 'label' => 'Mobile App'],
                ['value' => 'channel_partner', 'label' => 'Channel Partner'],
                ['value' => 'referral', 'label' => 'Referral'],
                ['value' => 'social', 'label' => 'Social'],
                ['value' => 'whatsapp', 'label' => 'WhatsApp'],
                ['value' => 'phone', 'label' => 'Phone'],
                ['value' => 'other', 'label' => 'Other'],
            ],
            'contact_methods' => [
                ['value' => 'phone', 'label' => 'Phone'],
                ['value' => 'email', 'label' => 'Email'],
                ['value' => 'whatsapp', 'label' => 'WhatsApp'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function inventoryPricingOptions(?User $user): ?array
    {
        if (! $user || (! $user->can('viewAny', ProjectUnit::class) && ! $user->can('viewAny', UnitPriceVersion::class))) {
            return null;
        }

        if ($this->isPartnerPortalUser($user) || $this->isBuyerPortalUser($user)) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);
        $projectIds = $this->visibleProjectIds($user);

        $unitQuery = ProjectUnit::query()
            ->with(['company:id,code,name', 'project:id,code,name,city', 'activeBooking:id,project_unit_id,booking_code,status'])
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]));

        $summaryRows = (clone $unitQuery)
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count);

        $inventoryValue = (float) (clone $unitQuery)->sum('total_price');

        $units = (clone $unitQuery)
            ->orderBy('project_id')
            ->orderBy('tower')
            ->orderByDesc('floor')
            ->orderBy('unit_number')
            ->limit(160)
            ->get();

        $activePriceVersions = UnitPriceVersion::query()
            ->whereIn('project_unit_id', $units->pluck('id')->all() ?: [0])
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', now()->toDateString())
            ->where(function (Builder $query): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', now()->toDateString());
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('version_number')
            ->get()
            ->unique('project_unit_id')
            ->keyBy('project_unit_id');

        $versionQuery = UnitPriceVersion::query()
            ->with(['company:id,code,name', 'project:id,code,name', 'unit:id,unit_code,unit_type,saleable_area_sqft,status', 'createdBy:id,name,email', 'approvedBy:id,name,email'])
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]));

        $priceVersions = (clone $versionQuery)
            ->orderByRaw("case when status = 'draft' then 0 when status = 'active' then 1 else 2 end")
            ->orderByDesc('effective_from')
            ->orderByDesc('version_number')
            ->limit(40)
            ->get();

        $projects = Project::query()
            ->with('company:id,code,name')
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('id', $projectIds ?: [0]))
            ->orderBy('code')
            ->get(['id', 'company_id', 'code', 'name', 'city'])
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'company_id' => $project->company_id,
                'company_code' => $project->company?->code,
                'code' => $project->code,
                'name' => $project->name,
                'city' => $project->city,
                'label' => $project->code.' · '.$project->name,
            ])
            ->values()
            ->all();

        $unitStatuses = ['available', 'reserved', 'booked', 'registered', 'handed_over', 'blocked', 'on_hold'];

        return [
            'source' => 'laravel-sqlite',
            'units_index_url' => route('inventory.units.index', [], false),
            'units_export_url' => route('inventory.units.export', [], false),
            'project_cost_roi_export_url' => route('projects.cost-roi.export', [], false),
            'price_versions_index_url' => route('inventory.unit-price-versions.index', [], false),
            'price_versions_store_url' => route('inventory.unit-price-versions.store', [], false),
            'price_versions_approve_url_template' => '/inventory/unit-price-versions/__PRICE_VERSION__/approve',
            'can_view_units' => $user->can('viewAny', ProjectUnit::class),
            'can_export_units' => $user->can('viewAny', ProjectUnit::class),
            'can_export_project_cost_roi' => $user->can('viewAny', Project::class),
            'can_create_price_version' => $user->can('create', UnitPriceVersion::class),
            'can_approve_price_version' => $user->hasPermission('*') || $user->hasPermission('booking.manage') || $user->hasPermission('finance.approve'),
            'projects' => $projects,
            'unit_statuses' => array_map(fn (string $status): array => [
                'value' => $status,
                'label' => str($status)->replace('_', ' ')->title()->toString(),
            ], $unitStatuses),
            'unit_types' => $units->pluck('unit_type')->filter()->unique()->sort()->values()->all(),
            'units' => $units
                ->map(fn (ProjectUnit $unit): array => $this->inventoryUnitBootstrapRow($unit, $activePriceVersions->get($unit->id)))
                ->values()
                ->all(),
            'price_versions' => $priceVersions
                ->map(fn (UnitPriceVersion $version): array => $this->unitPriceVersionBootstrapRow($version, $user))
                ->values()
                ->all(),
            'summary' => [
                'total_units' => (int) $summaryRows->sum(),
                'available' => (int) ($summaryRows['available'] ?? 0),
                'reserved' => (int) ($summaryRows['reserved'] ?? 0),
                'booked' => (int) ($summaryRows['booked'] ?? 0),
                'registered' => (int) ($summaryRows['registered'] ?? 0),
                'handed_over' => (int) ($summaryRows['handed_over'] ?? 0),
                'blocked' => (int) ($summaryRows['blocked'] ?? 0),
                'inventory_value' => round($inventoryValue, 2),
                'active_price_versions' => (clone $versionQuery)->where('status', 'active')->count(),
                'draft_price_versions' => (clone $versionQuery)->where('status', 'draft')->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inventoryUnitBootstrapRow(ProjectUnit $unit, ?UnitPriceVersion $activePriceVersion): array
    {
        return [
            'id' => $unit->id,
            'unit_code' => $unit->unit_code,
            'tower' => $unit->tower,
            'floor' => $unit->floor,
            'unit_number' => $unit->unit_number,
            'unit_type' => $unit->unit_type,
            'carpet_area_sqft' => (float) $unit->carpet_area_sqft,
            'saleable_area_sqft' => (float) $unit->saleable_area_sqft,
            'base_rate' => (float) $unit->base_rate,
            'base_price' => (float) $unit->base_price,
            'floor_rise' => (float) $unit->floor_rise,
            'parking_charges' => (float) $unit->parking_charges,
            'other_charges' => (float) $unit->other_charges,
            'tax_amount' => (float) $unit->tax_amount,
            'total_price' => (float) $unit->total_price,
            'status' => $unit->status,
            'reserved_until' => $unit->reserved_until?->toISOString(),
            'is_bookable' => $unit->isBookable(),
            'company' => $unit->company ? [
                'id' => $unit->company->id,
                'code' => $unit->company->code,
                'name' => $unit->company->name,
            ] : null,
            'project' => $unit->project ? [
                'id' => $unit->project->id,
                'code' => $unit->project->code,
                'name' => $unit->project->name,
                'city' => $unit->project->city,
            ] : null,
            'active_booking' => $unit->activeBooking ? [
                'id' => $unit->activeBooking->id,
                'booking_code' => $unit->activeBooking->booking_code,
                'status' => $unit->activeBooking->status,
            ] : null,
            'active_price_version' => $activePriceVersion ? [
                'id' => $activePriceVersion->id,
                'price_code' => $activePriceVersion->price_code,
                'version_number' => $activePriceVersion->version_number,
                'effective_from' => $activePriceVersion->effective_from?->toDateString(),
                'effective_to' => $activePriceVersion->effective_to?->toDateString(),
                'base_rate' => (float) $activePriceVersion->base_rate,
                'gross_price_before_tax' => (float) $activePriceVersion->gross_price_before_tax,
                'tax_rate_percent' => (float) $activePriceVersion->tax_rate_percent,
                'tax_amount' => (float) $activePriceVersion->tax_amount,
                'total_price' => (float) $activePriceVersion->total_price,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unitPriceVersionBootstrapRow(UnitPriceVersion $version, User $user): array
    {
        return [
            'id' => $version->id,
            'price_code' => $version->price_code,
            'version_number' => $version->version_number,
            'status' => $version->status,
            'effective_from' => $version->effective_from?->toDateString(),
            'effective_to' => $version->effective_to?->toDateString(),
            'base_rate' => (float) $version->base_rate,
            'base_price' => (float) $version->base_price,
            'floor_premium' => (float) $version->floor_premium,
            'location_premium' => (float) $version->location_premium,
            'parking_charges' => (float) $version->parking_charges,
            'other_charges' => (float) $version->other_charges,
            'tax_rate_percent' => (float) $version->tax_rate_percent,
            'gross_price_before_tax' => (float) $version->gross_price_before_tax,
            'tax_amount' => (float) $version->tax_amount,
            'total_price' => (float) $version->total_price,
            'workflow_history' => $version->workflow_history ?? [],
            'can_approve' => $version->status === 'draft'
                && $user->can('approve', $version)
                && ($version->created_by_user_id !== $user->id || $user->hasPermission('*')),
            'company' => $version->company ? [
                'id' => $version->company->id,
                'code' => $version->company->code,
                'name' => $version->company->name,
            ] : null,
            'project' => $version->project ? [
                'id' => $version->project->id,
                'code' => $version->project->code,
                'name' => $version->project->name,
            ] : null,
            'unit' => $version->unit ? [
                'id' => $version->unit->id,
                'unit_code' => $version->unit->unit_code,
                'unit_type' => $version->unit->unit_type,
                'saleable_area_sqft' => (float) $version->unit->saleable_area_sqft,
                'status' => $version->unit->status,
            ] : null,
            'created_by' => $version->createdBy ? [
                'id' => $version->createdBy->id,
                'name' => $version->createdBy->name,
                'email' => $version->createdBy->email,
            ] : null,
            'approved_by' => $version->approvedBy ? [
                'id' => $version->approvedBy->id,
                'name' => $version->approvedBy->name,
                'email' => $version->approvedBy->email,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function hrDashboardOptions(?User $user): ?array
    {
        if (! $user || $this->isPartnerPortalUser($user) || $this->isBuyerPortalUser($user)) {
            return null;
        }

        // Self-service policies deliberately allow employees to view their own
        // records. They are not authority to receive company-wide HR metrics.
        $canViewHrDashboard = $user->hasPermission('hr.view')
            || $user->hasPermission('hr.manage');

        if (! $canViewHrDashboard) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);
        $scopeCompanies = fn (Builder $query): Builder => $query
            ->when(is_array($companyIds), fn (Builder $scoped): Builder => $scoped->whereIn('company_id', $companyIds ?: [0]));

        $employeeQuery = $scopeCompanies(Employee::query());
        $activeStatuses = ['active', 'probation', 'on_notice'];
        $activeHeadcount = (clone $employeeQuery)->whereIn('status', $activeStatuses)->count();
        $totalHeadcount = (clone $employeeQuery)->count();

        $today = now()->toDateString();
        $attendanceQuery = $scopeCompanies(AttendanceRecord::query())->whereDate('work_date', $today);
        $attendanceMarked = (clone $attendanceQuery)->count();
        $attendancePresent = (clone $attendanceQuery)->whereIn('status', ['present', 'late', 'early_leave', 'half_day', 'overtime'])->count();
        $attendancePercent = $activeHeadcount > 0 ? round(($attendancePresent / max($activeHeadcount, 1)) * 100, 1) : null;

        $canViewPayrollDashboard = $user->hasPermission('payroll.view')
            || $user->hasPermission('payroll.manage')
            || $user->hasPermission('payroll.approve');
        $payrollQuery = $scopeCompanies(PayrollRun::query());
        $latestPayroll = $canViewPayrollDashboard
            ? (clone $payrollQuery)
                ->with('company:id,code,name')
                ->orderByDesc('period_year')
                ->orderByDesc('period_month')
                ->first()
            : null;

        $leavePending = $user->can('viewAny', LeaveRequest::class)
            ? (clone $scopeCompanies(LeaveRequest::query()))->where('status', 'submitted')->count()
            : 0;
        $attendancePending = $user->can('viewAny', AttendanceRegularizationRequest::class)
            ? (clone $scopeCompanies(AttendanceRegularizationRequest::query()))->where('status', 'submitted')->count()
            : 0;
        $confirmationPending = $user->can('viewAny', EmployeeConfirmationCase::class)
            ? (clone $scopeCompanies(EmployeeConfirmationCase::query()))->whereIn('status', ['due', 'manager_recommended'])->count()
            : 0;
        $settlementPending = $user->can('viewAny', EmployeeSeparationSettlement::class)
            ? (clone $scopeCompanies(EmployeeSeparationSettlement::query()))->whereIn('status', ['initiated', 'hr_approved', 'finance_approved'])->count()
            : 0;
        $payrollPending = $canViewPayrollDashboard
            ? (clone $payrollQuery)->whereIn('status', ['draft', 'generated'])->count()
            : 0;
        $performancePending = $user->can('viewAny', PerformanceReview::class)
            ? (clone $scopeCompanies(PerformanceReview::query()))->whereIn('status', ['draft', 'self_submitted', 'manager_submitted'])->count()
            : 0;

        $openPositions = $user->can('viewAny', JobOpening::class)
            ? (clone $scopeCompanies(JobOpening::query()))->whereIn('status', ['open', 'pending_approval'])->sum('positions')
            : 0;
        $candidatePipeline = $user->can('viewAny', Candidate::class)
            ? (clone $scopeCompanies(Candidate::query()))->whereNotIn('status', ['converted', 'rejected', 'withdrawn', 'inactive'])->count()
            : 0;
        $openHelpdeskTickets = $user->can('viewAny', HrHelpdeskTicket::class)
            ? (clone $scopeCompanies(HrHelpdeskTicket::query()))->whereNotIn('status', ['resolved', 'closed'])->count()
            : 0;

        $requiredSettingKeys = config('builder360.system_settings.required_active_keys', []);
        $activeSettingsQuery = SystemSetting::query()
            ->whereIn('setting_key', $requiredSettingKeys)
            ->where('status', 'active')
            ->when(is_array($companyIds), function (Builder $query) use ($companyIds): void {
                $query->where(function (Builder $scoped) use ($companyIds): void {
                    $scoped->whereNull('company_id')
                        ->orWhereIn('company_id', $companyIds ?: [0]);
                });
            });

        $activeSettingKeys = (clone $activeSettingsQuery)
            ->pluck('setting_key')
            ->unique()
            ->values()
            ->all();
        $missingSettingKeys = array_values(array_diff($requiredSettingKeys, $activeSettingKeys));

        $companyHeadcountCounts = (clone $employeeQuery)
            ->select('company_id', DB::raw('count(*) as aggregate'))
            ->groupBy('company_id')
            ->pluck('aggregate', 'company_id')
            ->map(fn ($count): int => (int) $count);

        $companyHeadcount = Company::query()
            ->when(is_array($companyIds), fn (Builder $query): Builder => $query->whereIn('id', $companyIds ?: [0]))
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Company $company): array => [
                'id' => $company->id,
                'code' => $company->code,
                'name' => $company->name,
                'employees' => (int) ($companyHeadcountCounts[$company->id] ?? 0),
            ])
            ->values()
            ->all();

        $departmentHeadcount = (clone $employeeQuery)
            ->select('department', DB::raw('count(*) as aggregate'))
            ->groupBy('department')
            ->orderByDesc('aggregate')
            ->limit(12)
            ->get()
            ->map(fn ($row): array => [
                'department' => $row->department ?: 'Unassigned',
                'employees' => (int) $row->aggregate,
            ])
            ->values()
            ->all();

        $approvalRows = collect();

        if ($user->can('viewAny', LeaveRequest::class)) {
            (clone $scopeCompanies(LeaveRequest::query()))
                ->with(['employee:id,employee_code,name,department', 'leaveType:id,code,name'])
                ->where('status', 'submitted')
                ->latest()
                ->limit(8)
                ->get()
                ->each(function (LeaveRequest $request) use ($approvalRows, $user): void {
                    $approvalRows->push([
                        'id' => 'leave-'.$request->id,
                        'record_id' => $request->id,
                        'type' => 'Leave',
                        'reference' => $request->request_number,
                        'subject' => ($request->employee?->name ?? 'Employee').' · '.($request->leaveType?->name ?? 'Leave').' · '.$request->requested_days.' day(s)',
                        'owner' => $request->employee?->department ?? 'HR',
                        'age' => $request->created_at?->diffForHumans(short: true) ?? 'new',
                        'status' => $request->status,
                        'created_at' => $request->created_at?->toISOString(),
                        'can_approve' => $user->can('approve', $request),
                    ]);
                });
        }

        if ($user->can('viewAny', AttendanceRegularizationRequest::class)) {
            (clone $scopeCompanies(AttendanceRegularizationRequest::query()))
                ->with('employee:id,employee_code,name,department')
                ->where('status', 'submitted')
                ->latest()
                ->limit(8)
                ->get()
                ->each(function (AttendanceRegularizationRequest $request) use ($approvalRows, $user): void {
                    $approvalRows->push([
                        'id' => 'attendance-'.$request->id,
                        'record_id' => $request->id,
                        'type' => 'Attendance',
                        'reference' => $request->request_number,
                        'subject' => ($request->employee?->name ?? 'Employee').' · regularization for '.$request->work_date?->toDateString(),
                        'owner' => $request->employee?->department ?? 'HR',
                        'age' => $request->created_at?->diffForHumans(short: true) ?? 'new',
                        'status' => $request->status,
                        'created_at' => $request->created_at?->toISOString(),
                        'can_approve' => $user->can('approve', $request),
                    ]);
                });
        }

        if ($user->can('viewAny', EmployeeConfirmationCase::class)) {
            (clone $scopeCompanies(EmployeeConfirmationCase::query()))
                ->with(['employee:id,employee_code,name,department', 'managerEmployee:id,name'])
                ->whereIn('status', ['due', 'manager_recommended'])
                ->orderBy('review_due_on')
                ->limit(8)
                ->get()
                ->each(function (EmployeeConfirmationCase $case) use ($approvalRows, $user): void {
                    $approvalRows->push([
                        'id' => 'confirmation-'.$case->id,
                        'record_id' => $case->id,
                        'type' => 'Confirmation',
                        'reference' => $case->case_number,
                        'subject' => ($case->employee?->name ?? 'Employee').' · probation due '.$case->review_due_on?->toDateString(),
                        'owner' => $case->managerEmployee?->name ?? 'HR',
                        'age' => $case->created_at?->diffForHumans(short: true) ?? 'new',
                        'status' => $case->status,
                        'created_at' => $case->created_at?->toISOString(),
                        'can_approve' => $user->can('recommend', $case) || $user->can('decide', $case),
                    ]);
                });
        }

        if ($canViewPayrollDashboard) {
            (clone $payrollQuery)
                ->with('company:id,code,name')
                ->whereIn('status', ['draft', 'generated'])
                ->latest()
                ->limit(5)
                ->get()
                ->each(function (PayrollRun $run) use ($approvalRows, $user): void {
                    $approvalRows->push([
                        'id' => 'payroll-'.$run->id,
                        'record_id' => $run->id,
                        'type' => 'Payroll',
                        'reference' => $run->run_number,
                        'subject' => ($run->company?->code ?? 'Company').' · '.$run->period_month.'/'.$run->period_year.' · '.$this->currency($run->net_payable),
                        'owner' => 'Payroll / Finance',
                        'age' => $run->created_at?->diffForHumans(short: true) ?? 'new',
                        'status' => $run->status,
                        'created_at' => $run->created_at?->toISOString(),
                        'can_approve' => $user->can('approve', $run),
                    ]);
                });
        }

        if ($user->can('viewAny', EmployeeSeparationSettlement::class)) {
            (clone $scopeCompanies(EmployeeSeparationSettlement::query()))
                ->with('employee:id,employee_code,name,department')
                ->whereIn('status', ['initiated', 'hr_approved', 'finance_approved'])
                ->latest()
                ->limit(6)
                ->get()
                ->each(function (EmployeeSeparationSettlement $settlement) use ($approvalRows, $user): void {
                    $approvalRows->push([
                        'id' => 'settlement-'.$settlement->id,
                        'record_id' => $settlement->id,
                        'type' => 'F&F',
                        'reference' => $settlement->settlement_number,
                        'subject' => ($settlement->employee?->name ?? 'Employee').' · net '.$this->currency($settlement->net_payable),
                        'owner' => 'HR / Finance',
                        'age' => $settlement->created_at?->diffForHumans(short: true) ?? 'new',
                        'status' => $settlement->status,
                        'created_at' => $settlement->created_at?->toISOString(),
                        'can_approve' => $user->can('hrApprove', $settlement)
                            || $user->can('financeApprove', $settlement)
                            || $user->can('complete', $settlement),
                    ]);
                });
        }

        $lifecycleDue = collect();

        if ($user->can('viewAny', EmployeeConfirmationCase::class)) {
            (clone $scopeCompanies(EmployeeConfirmationCase::query()))
                ->with(['employee:id,employee_code,name,department', 'managerEmployee:id,name'])
                ->whereIn('status', ['due', 'manager_recommended'])
                ->orderBy('review_due_on')
                ->limit(8)
                ->get()
                ->each(fn (EmployeeConfirmationCase $case) => $lifecycleDue->push([
                    'id' => 'confirmation-'.$case->id,
                    'employee' => $case->employee?->name ?? 'Employee',
                    'employee_code' => $case->employee?->employee_code,
                    'event' => 'Probation / Confirmation',
                    'due' => $case->review_due_on?->toDateString(),
                    'owner' => $case->managerEmployee?->name ?? 'HR',
                    'status' => $case->status,
                ]));
        }

        if ($user->can('viewAny', EmployeeSeparationSettlement::class)) {
            (clone $scopeCompanies(EmployeeSeparationSettlement::query()))
                ->with('employee:id,employee_code,name,department')
                ->whereIn('status', ['initiated', 'hr_approved', 'finance_approved'])
                ->orderBy('last_working_date')
                ->limit(8)
                ->get()
                ->each(fn (EmployeeSeparationSettlement $settlement) => $lifecycleDue->push([
                    'id' => 'settlement-'.$settlement->id,
                    'employee' => $settlement->employee?->name ?? 'Employee',
                    'employee_code' => $settlement->employee?->employee_code,
                    'event' => 'Separation / F&F',
                    'due' => $settlement->last_working_date?->toDateString(),
                    'owner' => 'HR / Finance',
                    'status' => $settlement->status,
                ]));
        }

        $activeSettings = (clone $activeSettingsQuery)
            ->with('company:id,code,name')
            ->orderBy('setting_key')
            ->limit(20)
            ->get();

        $complianceRisk = collect($missingSettingKeys)
            ->map(fn (string $key): array => [
                'key' => $key,
                'name' => str($key)->replace(['.', '_'], ' ')->title()->toString(),
                'version' => 'missing',
                'effective' => null,
                'verification' => 'Missing active setting',
                'company' => 'Required',
                'tone' => 'b-red',
            ])
            ->merge($activeSettings->map(fn (SystemSetting $setting): array => [
                'key' => $setting->setting_key,
                'name' => $setting->label,
                'version' => 'v'.$setting->version,
                'effective' => $setting->effective_from?->toDateString() ?? 'Immediate',
                'verification' => $setting->status,
                'company' => $setting->company?->code ?? 'Global',
                'tone' => 'b-green',
            ]))
            ->values()
            ->all();

        return [
            'source' => 'laravel-sqlite',
            'generated_at' => now()->toISOString(),
            'scope' => [
                'company_ids' => is_array($companyIds) ? $companyIds : 'global',
                'role' => $user->role?->slug,
            ],
            'summary' => [
                'active_headcount' => (int) $activeHeadcount,
                'total_headcount' => (int) $totalHeadcount,
                'attendance_today_percent' => $attendancePercent,
                'attendance_present_today' => (int) $attendancePresent,
                'attendance_marked_today' => (int) $attendanceMarked,
                'pending_approvals' => (int) ($leavePending + $attendancePending + $confirmationPending + $settlementPending + $payrollPending + $performancePending),
                'pending_leave_requests' => (int) $leavePending,
                'pending_attendance_regularizations' => (int) $attendancePending,
                'pending_confirmations' => (int) $confirmationPending,
                'pending_settlements' => (int) $settlementPending,
                'pending_payroll_runs' => (int) $payrollPending,
                'pending_performance_reviews' => (int) $performancePending,
                'open_positions' => (int) $openPositions,
                'candidate_pipeline' => (int) $candidatePipeline,
                'open_helpdesk_tickets' => (int) $openHelpdeskTickets,
                'compliance_alerts' => count($missingSettingKeys),
                'latest_payroll_net_payable' => $latestPayroll ? (float) $latestPayroll->net_payable : null,
                'latest_payroll_label' => $canViewPayrollDashboard
                    ? ($latestPayroll ? (($latestPayroll->company?->code ?? 'Company').' · '.$latestPayroll->period_month.'/'.$latestPayroll->period_year.' · '.$latestPayroll->status) : 'No payroll run')
                    : 'Restricted',
            ],
            'company_headcount' => $companyHeadcount,
            'department_headcount' => $departmentHeadcount,
            'approval_inbox' => $approvalRows
                ->sortByDesc(fn (array $row): string => (string) ($row['created_at'] ?? ''))
                ->take(25)
                ->values()
                ->all(),
            'lifecycle_due' => $lifecycleDue
                ->sortBy(fn (array $row): string => (string) ($row['due'] ?? '9999-12-31'))
                ->take(16)
                ->values()
                ->all(),
            'compliance_risk' => $complianceRisk,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function hrHelpdeskOptions(?User $user): ?array
    {
        if (! $user || ! $user->can('create', HrHelpdeskTicket::class)) {
            return null;
        }

        $employee = Employee::query()
            ->where('user_id', $user->id)
            ->first(['id', 'company_id', 'employee_code', 'name', 'designation', 'department']);

        return [
            'source' => 'laravel-sqlite',
            'store_url' => route('hr.helpdesk-tickets.store', [], false),
            'can_create' => (bool) $employee,
            'self_employee' => $employee ? [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'name' => $employee->name,
                'designation' => $employee->designation,
                'department' => $employee->department,
            ] : null,
            'categories' => [
                ['value' => 'payroll', 'label' => 'Payroll'],
                ['value' => 'attendance', 'label' => 'Attendance'],
                ['value' => 'leave', 'label' => 'Leave'],
                ['value' => 'documents', 'label' => 'Documents'],
                ['value' => 'assets', 'label' => 'Assets'],
                ['value' => 'policy', 'label' => 'Policy'],
                ['value' => 'other', 'label' => 'Other'],
            ],
            'priorities' => [
                ['value' => 'low', 'label' => 'Low'],
                ['value' => 'medium', 'label' => 'Medium'],
                ['value' => 'high', 'label' => 'High'],
                ['value' => 'critical', 'label' => 'Critical'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function hrSelfServiceOptions(?User $user): ?array
    {
        if (! $user || $this->isPartnerPortalUser($user) || $this->isBuyerPortalUser($user)) {
            return null;
        }

        $employee = Employee::query()
            ->where('user_id', $user->id)
            ->first(['id', 'company_id', 'employee_code', 'name', 'designation', 'department']);

        if (! $employee) {
            return null;
        }

        $canCreateClaim = $user->can('create', ExpenseClaim::class);
        $canViewClaims = $user->can('viewAny', ExpenseClaim::class);
        $canCreateLeaveRequest = $user->can('create', LeaveRequest::class);
        $canViewLeaveRequests = $user->can('viewAny', LeaveRequest::class);
        $canCreateAttendanceRegularization = $user->can('create', AttendanceRegularizationRequest::class);
        $canViewAttendanceRegularizations = $user->can('viewAny', AttendanceRegularizationRequest::class);
        $canViewPerformanceReviews = $user->can('viewAny', PerformanceReview::class);
        $canCreatePolicyAcknowledgement = $user->can('create', EmployeePolicyAcknowledgement::class);
        $canViewPolicyAcknowledgements = $user->can('viewAny', EmployeePolicyAcknowledgement::class);
        $canViewPayrollSummary = $user->hasPermission('employee.self_service')
            || $user->hasPermission('*')
            || $user->hasPermission('hr.manage')
            || $user->hasPermission('payroll.view')
            || $user->hasPermission('payroll.manage')
            || $user->hasPermission('payroll.approve');

        if (
            ! $canCreateClaim
            && ! $canViewClaims
            && ! $canCreateLeaveRequest
            && ! $canViewLeaveRequests
            && ! $canCreateAttendanceRegularization
            && ! $canViewAttendanceRegularizations
            && ! $canViewPerformanceReviews
            && ! $canCreatePolicyAcknowledgement
            && ! $canViewPolicyAcknowledgements
            && ! $canViewPayrollSummary
        ) {
            return null;
        }

        $leaveBalances = collect();
        if ($canCreateLeaveRequest || $canViewLeaveRequests) {
            $leaveBalances = EmployeeLeaveBalance::query()
                ->with('leaveType:id,code,name,requires_document,allows_half_day')
                ->where('employee_id', $employee->id)
                ->where('period_year', now()->year)
                ->get()
                ->keyBy('leave_type_id');
        }

        $leaveTypes = LeaveType::query()
            ->where('company_id', $employee->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'requires_document', 'allows_half_day', 'allow_negative_balance'])
            ->map(function (LeaveType $leaveType) use ($leaveBalances): array {
                $balance = $leaveBalances->get($leaveType->id);

                return [
                    'id' => $leaveType->id,
                    'code' => $leaveType->code,
                    'name' => $leaveType->name,
                    'label' => $leaveType->code.' · '.$leaveType->name,
                    'requires_document' => $leaveType->requires_document,
                    'allows_half_day' => $leaveType->allows_half_day,
                    'allow_negative_balance' => $leaveType->allow_negative_balance,
                    'available_days' => $balance ? (float) $balance->available_days : 0.0,
                    'pending_days' => $balance ? (float) $balance->pending_days : 0.0,
                ];
            })
            ->values()
            ->all();

        $today = now();
        $attendanceQuery = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [
                $today->copy()->startOfMonth()->toDateString(),
                $today->copy()->endOfMonth()->toDateString(),
            ]);
        $attendanceMarked = (clone $attendanceQuery)->count();
        $attendancePresent = (clone $attendanceQuery)
            ->whereIn('status', ['present', 'late', 'half_day', 'on_leave', 'weekly_off', 'holiday'])
            ->count();
        $recentAttendance = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [
                $today->copy()->subDays(13)->toDateString(),
                $today->copy()->toDateString(),
            ])
            ->orderBy('work_date')
            ->get(['id', 'work_date', 'status', 'late_minutes', 'early_leave_minutes', 'worked_minutes'])
            ->map(fn (AttendanceRecord $record): array => [
                'id' => $record->id,
                'work_date' => $record->work_date?->toDateString(),
                'day_label' => $record->work_date?->format('d M') ?? '—',
                'status' => $record->status,
                'status_code' => match ($record->status) {
                    'present' => 'P',
                    'late' => 'L',
                    'half_day' => 'HD',
                    'absent' => 'A',
                    'on_leave' => 'LV',
                    'weekly_off' => 'WO',
                    'holiday' => 'H',
                    default => strtoupper(substr((string) $record->status, 0, 2)),
                },
                'late_minutes' => (int) $record->late_minutes,
                'early_leave_minutes' => (int) $record->early_leave_minutes,
                'worked_minutes' => (int) $record->worked_minutes,
            ])
            ->values()
            ->all();

        $openRequests = 0;
        if ($canViewClaims) {
            $openRequests += ExpenseClaim::query()
                ->where('employee_id', $employee->id)
                ->whereIn('status', ['submitted', 'approved'])
                ->count();
        }
        if ($canViewLeaveRequests) {
            $openRequests += LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->where('status', 'submitted')
                ->count();
        }
        if ($canViewAttendanceRegularizations) {
            $openRequests += AttendanceRegularizationRequest::query()
                ->where('employee_id', $employee->id)
                ->where('status', 'submitted')
                ->count();
        }
        if ($user->can('create', HrHelpdeskTicket::class)) {
            $openRequests += HrHelpdeskTicket::query()
                ->where('employee_id', $employee->id)
                ->whereNotIn('status', ['resolved', 'closed', 'cancelled'])
                ->count();
        }

        $latestPayrollItem = $canViewPayrollSummary
            ? PayrollRunItem::query()
                ->with('payrollRun:id,run_number,period_year,period_month,status')
                ->where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->whereHas('payrollRun', fn (Builder $query) => $query->where('status', 'approved'))
                ->latest('id')
                ->first(['id', 'payroll_run_id', 'employee_id', 'status', 'net_payable'])
            : null;
        $latestPayrollRun = $latestPayrollItem?->payrollRun;

        return [
            'source' => 'laravel-sqlite',
            'claims_index_url' => $canViewClaims ? route('hr.expense-claims.index', [], false) : null,
            'claim_store_url' => $canCreateClaim ? route('hr.expense-claims.store', [], false) : null,
            'leave_requests_index_url' => $canViewLeaveRequests ? route('hr.leave-requests.index', [], false) : null,
            'leave_request_store_url' => $canCreateLeaveRequest ? route('hr.leave-requests.store', [], false) : null,
            'leave_balances_index_url' => $canViewLeaveRequests ? route('hr.leave-balances.index', [], false) : null,
            'attendance_regularizations_index_url' => $canViewAttendanceRegularizations ? route('hr.attendance-regularizations.index', [], false) : null,
            'attendance_regularization_store_url' => $canCreateAttendanceRegularization ? route('hr.attendance-regularizations.store', [], false) : null,
            'attendance_records_index_url' => $canViewAttendanceRegularizations ? route('hr.attendance-records.index', [], false) : null,
            'attendance_shifts_index_url' => $canViewAttendanceRegularizations ? route('hr.attendance-shifts.index', [], false) : null,
            'payroll_summary_url' => $canViewPayrollSummary ? route('hr.employees.payroll-summary.show', $employee, false) : null,
            'tax_document_acknowledge_url_template' => $canViewPayrollSummary && $user->hasPermission('employee.self_service') ? '/payroll/tax-documents/__DOCUMENT__/acknowledge' : null,
            'performance_reviews_index_url' => $canViewPerformanceReviews ? route('hr.performance-reviews.index', ['employee_id' => $employee->id], false) : null,
            'performance_review_self_submit_url_template' => $canViewPerformanceReviews ? '/hr/performance-reviews/__REVIEW__/self-submit' : null,
            'policy_acknowledgements_index_url' => $canViewPolicyAcknowledgements ? route('hr.policy-acknowledgements.index', ['employee_id' => $employee->id], false) : null,
            'policy_acknowledgement_store_url' => $canCreatePolicyAcknowledgement ? route('hr.policy-acknowledgements.store', [], false) : null,
            'can_create_claim' => $canCreateClaim,
            'can_view_claims' => $canViewClaims,
            'can_create_leave_request' => $canCreateLeaveRequest,
            'can_view_leave_requests' => $canViewLeaveRequests,
            'can_create_attendance_regularization' => $canCreateAttendanceRegularization,
            'can_view_attendance_regularizations' => $canViewAttendanceRegularizations,
            'can_view_performance_reviews' => $canViewPerformanceReviews,
            'can_submit_self_review' => $canViewPerformanceReviews && $user->hasPermission('employee.self_service'),
            'can_create_policy_acknowledgement' => $canCreatePolicyAcknowledgement,
            'can_view_policy_acknowledgements' => $canViewPolicyAcknowledgements,
            'can_view_payroll_summary' => $canViewPayrollSummary,
            'can_acknowledge_tax_documents' => $canViewPayrollSummary && $user->hasPermission('employee.self_service'),
            'self_employee' => [
                'id' => $employee->id,
                'company_id' => $employee->company_id,
                'employee_code' => $employee->employee_code,
                'name' => $employee->name,
                'designation' => $employee->designation,
                'department' => $employee->department,
            ],
            'summary' => [
                'attendance_percent' => $attendanceMarked > 0 ? round($attendancePresent / $attendanceMarked * 100, 1) : null,
                'attendance_marked_days' => (int) $attendanceMarked,
                'attendance_present_days' => (int) $attendancePresent,
                'leave_available_days' => round((float) collect($leaveTypes)->sum('available_days'), 2),
                'open_requests' => (int) $openRequests,
                'latest_payslip_period' => $latestPayrollRun ? sprintf('%02d/%04d', $latestPayrollRun->period_month, $latestPayrollRun->period_year) : null,
                'latest_payslip_status' => $latestPayrollRun ? $latestPayrollRun->status : 'No approved payroll',
                'latest_payslip_net_payable' => $latestPayrollItem ? (float) $latestPayrollItem->net_payable : null,
            ],
            'recent_attendance' => $recentAttendance,
            'claim_types' => [
                ['value' => 'travel', 'label' => 'Travel'],
                ['value' => 'food', 'label' => 'Food'],
                ['value' => 'fuel', 'label' => 'Fuel'],
                ['value' => 'mobile', 'label' => 'Mobile'],
                ['value' => 'medical', 'label' => 'Medical'],
                ['value' => 'office', 'label' => 'Office'],
                ['value' => 'other', 'label' => 'Other'],
            ],
            'leave_types' => $leaveTypes,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function hrLeaveOptions(?User $user): ?array
    {
        if (! $user || $this->isPartnerPortalUser($user) || $this->isBuyerPortalUser($user)) {
            return null;
        }

        $canViewRequests = $user->can('viewAny', LeaveRequest::class);
        $canCreateRequest = $user->can('create', LeaveRequest::class);
        $canViewProcessingRuns = $user->can('viewAny', LeaveProcessingRun::class);
        $canCreateProcessingRun = $user->can('create', LeaveProcessingRun::class);
        $canPostProcessingRun = $user->hasPermission('leave.approve');
        $canViewEncashments = $user->can('viewAny', LeaveEncashment::class);
        $canCreateEncashment = $user->can('create', LeaveEncashment::class);
        $canApproveEncashment = $user->hasPermission('leave.approve') || $user->hasPermission('leave.manage');
        $canMarkEncashmentPayroll = $user->hasPermission('payroll.manage');

        if (
            ! $canViewRequests
            && ! $canCreateRequest
            && ! $canViewProcessingRuns
            && ! $canCreateProcessingRun
            && ! $canViewEncashments
            && ! $canCreateEncashment
        ) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);

        $companies = Company::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('id', $companyIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'state'])
            ->map(fn (Company $company): array => [
                'id' => $company->id,
                'code' => $company->code,
                'name' => $company->name,
                'state' => $company->state,
                'label' => $company->code.' · '.$company->name,
            ])
            ->values()
            ->all();

        $selfEmployee = Employee::query()
            ->where('user_id', $user->id)
            ->first(['id', 'company_id', 'employee_code', 'name', 'designation', 'department']);

        $employeeQuery = Employee::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->whereIn('status', ['active', 'probation', 'on_notice'])
            ->orderBy('employee_code');

        if (! $user->hasPermission('leave.manage') && ! $user->hasPermission('leave.approve') && ! $user->hasPermission('payroll.manage')) {
            $employeeQuery->where('user_id', $user->id);
        }

        $employees = $employeeQuery
            ->limit(120)
            ->get(['id', 'company_id', 'employee_code', 'name', 'designation', 'department', 'status'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'company_id' => $employee->company_id,
                'employee_code' => $employee->employee_code,
                'name' => $employee->name,
                'designation' => $employee->designation,
                'department' => $employee->department,
                'status' => $employee->status,
                'label' => $employee->employee_code.' · '.$employee->name,
            ])
            ->values()
            ->all();

        $leaveTypes = LeaveType::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'company_id', 'code', 'name', 'is_paid', 'requires_document', 'allows_half_day', 'allow_negative_balance', 'encashment_enabled'])
            ->map(fn (LeaveType $leaveType): array => [
                'id' => $leaveType->id,
                'company_id' => $leaveType->company_id,
                'code' => $leaveType->code,
                'name' => $leaveType->name,
                'label' => $leaveType->code.' · '.$leaveType->name,
                'is_paid' => $leaveType->is_paid,
                'requires_document' => $leaveType->requires_document,
                'allows_half_day' => $leaveType->allows_half_day,
                'allow_negative_balance' => $leaveType->allow_negative_balance,
                'encashment_enabled' => $leaveType->encashment_enabled,
            ])
            ->values()
            ->all();

        return [
            'source' => 'laravel-sqlite',
            'leave_requests_index_url' => $canViewRequests ? route('hr.leave-requests.index', [], false) : null,
            'leave_request_store_url' => $canCreateRequest ? route('hr.leave-requests.store', [], false) : null,
            'leave_request_approve_url_template' => $user->hasPermission('leave.approve') || $user->hasPermission('leave.manage') ? '/hr/leave-requests/__REQUEST__/approve' : null,
            'leave_request_reject_url_template' => $user->hasPermission('leave.approve') || $user->hasPermission('leave.manage') ? '/hr/leave-requests/__REQUEST__/reject' : null,
            'leave_balances_index_url' => $canViewRequests ? route('hr.leave-balances.index', [], false) : null,
            'leave_processing_runs_index_url' => $canViewProcessingRuns ? route('hr.leave-processing-runs.index', [], false) : null,
            'leave_processing_runs_store_url' => $canCreateProcessingRun ? route('hr.leave-processing-runs.store', [], false) : null,
            'leave_processing_run_post_url_template' => $canPostProcessingRun ? '/hr/leave-processing-runs/__RUN__/post' : null,
            'leave_encashments_index_url' => $canViewEncashments ? route('hr.leave-encashments.index', [], false) : null,
            'leave_encashments_store_url' => $canCreateEncashment ? route('hr.leave-encashments.store', [], false) : null,
            'leave_encashment_approve_url_template' => $canApproveEncashment ? '/hr/leave-encashments/__ENCASHMENT__/approve' : null,
            'leave_encashment_reject_url_template' => $canApproveEncashment ? '/hr/leave-encashments/__ENCASHMENT__/reject' : null,
            'leave_encashment_mark_payroll_url_template' => $canMarkEncashmentPayroll ? '/hr/leave-encashments/__ENCASHMENT__/mark-payroll' : null,
            'can_view_leave_requests' => $canViewRequests,
            'can_create_leave_request' => $canCreateRequest,
            'can_approve_leave_request' => $user->hasPermission('leave.approve') || $user->hasPermission('leave.manage'),
            'can_view_processing_runs' => $canViewProcessingRuns,
            'can_create_processing_run' => $canCreateProcessingRun,
            'can_post_processing_run' => $canPostProcessingRun,
            'can_view_encashments' => $canViewEncashments,
            'can_create_encashment' => $canCreateEncashment,
            'can_approve_encashment' => $canApproveEncashment,
            'can_mark_encashment_payroll' => $canMarkEncashmentPayroll,
            'companies' => $companies,
            'employees' => $employees,
            'leave_types' => $leaveTypes,
            'processing_types' => [
                ['value' => 'monthly_accrual', 'label' => 'Monthly Accrual'],
                ['value' => 'year_end', 'label' => 'Year-End Carry/Lapse'],
            ],
            'current_period_year' => (int) now()->year,
            'self_employee' => $selfEmployee ? [
                'id' => $selfEmployee->id,
                'company_id' => $selfEmployee->company_id,
                'employee_code' => $selfEmployee->employee_code,
                'name' => $selfEmployee->name,
                'designation' => $selfEmployee->designation,
                'department' => $selfEmployee->department,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function hrAttendanceOptions(?User $user): ?array
    {
        if (! $user || ! $user->can('viewAny', AttendanceRegularizationRequest::class)) {
            return null;
        }

        if ($this->isPartnerPortalUser($user) || $this->isBuyerPortalUser($user)) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);
        $companies = Company::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('id', $companyIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'state'])
            ->map(fn (Company $company): array => [
                'id' => $company->id,
                'code' => $company->code,
                'name' => $company->name,
                'state' => $company->state,
                'label' => $company->code.' · '.$company->name,
            ])
            ->values()
            ->all();

        return [
            'source' => 'laravel-sqlite',
            'attendance_records_index_url' => route('hr.attendance-records.index', [], false),
            'attendance_regularizations_index_url' => route('hr.attendance-regularizations.index', [], false),
            'attendance_regularization_approve_url_template' => $user->hasPermission('attendance.approve') ? '/hr/attendance-regularizations/__REGULARIZATION__/approve' : null,
            'attendance_regularization_reject_url_template' => $user->hasPermission('attendance.approve') ? '/hr/attendance-regularizations/__REGULARIZATION__/reject' : null,
            'shifts_index_url' => route('hr.attendance-shifts.index', [], false),
            'shifts_store_url' => $user->hasPermission('attendance.manage') ? route('hr.attendance-shifts.store', [], false) : null,
            'can_view_attendance_records' => true,
            'can_view_attendance_regularizations' => true,
            'can_approve_regularization' => $user->hasPermission('attendance.approve'),
            'can_view_shifts' => true,
            'can_create_shift' => $user->hasPermission('attendance.manage'),
            'status_filters' => [
                ['value' => 'present', 'label' => 'Present'],
                ['value' => 'absent', 'label' => 'Absent'],
                ['value' => 'late', 'label' => 'Late'],
                ['value' => 'half_day', 'label' => 'Half-day'],
                ['value' => 'on_leave', 'label' => 'On Leave'],
                ['value' => 'weekly_off', 'label' => 'Weekly Off'],
                ['value' => 'holiday', 'label' => 'Holiday'],
            ],
            'regularization_status_filters' => [
                ['value' => 'submitted', 'label' => 'Submitted'],
                ['value' => 'approved', 'label' => 'Approved'],
                ['value' => 'rejected', 'label' => 'Rejected'],
            ],
            'source_filters' => [
                ['value' => 'manual', 'label' => 'Manual/Web'],
                ['value' => 'biometric', 'label' => 'Biometric feed'],
                ['value' => 'mobile_gps', 'label' => 'Mobile GPS'],
                ['value' => 'import', 'label' => 'Import'],
            ],
            'companies' => $companies,
            'shift_types' => [
                ['value' => 'fixed', 'label' => 'Fixed'],
                ['value' => 'flexible', 'label' => 'Flexible'],
                ['value' => 'rotational', 'label' => 'Rotational'],
                ['value' => 'night', 'label' => 'Night'],
                ['value' => 'split', 'label' => 'Split'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function hrRecruitmentOptions(?User $user): ?array
    {
        if (! $user || ! $user->can('viewAny', JobOpening::class)) {
            return null;
        }

        if ($this->isPartnerPortalUser($user) || $this->isBuyerPortalUser($user)) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);
        $projectIds = $this->visibleProjectIds($user);

        $companies = Company::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('id', $companyIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'state'])
            ->map(fn (Company $company): array => [
                'id' => $company->id,
                'code' => $company->code,
                'name' => $company->name,
                'state' => $company->state,
                'label' => $company->code.' · '.$company->name,
            ])
            ->values()
            ->all();

        $branches = Branch::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'company_id', 'code', 'name'])
            ->map(fn (Branch $branch): array => [
                'id' => $branch->id,
                'company_id' => $branch->company_id,
                'code' => $branch->code,
                'name' => $branch->name,
                'label' => $branch->code.' · '.$branch->name,
            ])
            ->values()
            ->all();

        $projects = Project::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('id', $projectIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'company_id', 'branch_id', 'code', 'name'])
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'company_id' => $project->company_id,
                'branch_id' => $project->branch_id,
                'code' => $project->code,
                'name' => $project->name,
                'label' => $project->code.' · '.$project->name,
            ])
            ->values()
            ->all();

        $openingQuery = JobOpening::query()
            ->with(['company:id,code,name', 'branch:id,code,name', 'project:id,code,name', 'createdBy:id,name,email', 'reviewedBy:id,name,email'])
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]));

        $openings = (clone $openingQuery)
            ->orderByRaw("case when status = 'pending_approval' then 0 when status = 'open' then 1 else 2 end")
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (JobOpening $opening): array => $this->jobOpeningBootstrapRow($opening, $user))
            ->values()
            ->all();

        $statusCounts = (clone $openingQuery)
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count);

        $candidateQuery = Candidate::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]));
        $interviewQuery = Interview::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]));
        $offerQuery = JobOffer::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]));

        return [
            'source' => 'laravel-sqlite',
            'job_openings_index_url' => route('recruitment.job-openings.index', [], false),
            'job_openings_store_url' => $user->can('create', JobOpening::class) ? route('recruitment.job-openings.store', [], false) : null,
            'job_openings_approve_url_template' => '/recruitment/job-openings/__OPENING__/approve',
            'job_openings_reject_url_template' => '/recruitment/job-openings/__OPENING__/reject',
            'candidates_index_url' => $user->can('viewAny', Candidate::class) ? route('recruitment.candidates.index', [], false) : null,
            'candidates_store_url' => $user->can('create', Candidate::class) ? route('recruitment.candidates.store', [], false) : null,
            'candidates_stage_url_template' => '/recruitment/candidates/__CANDIDATE__/stage',
            'candidates_convert_url_template' => '/recruitment/candidates/__CANDIDATE__/convert-to-employee',
            'interviews_index_url' => $user->can('viewAny', Candidate::class) ? route('recruitment.interviews.index', [], false) : null,
            'interviews_store_url' => $user->can('create', Candidate::class) ? route('recruitment.interviews.store', [], false) : null,
            'interviews_feedback_url_template' => '/recruitment/interviews/__INTERVIEW__/feedback',
            'offers_index_url' => $user->can('viewAny', JobOffer::class) ? route('recruitment.offers.index', [], false) : null,
            'offers_store_url' => $user->can('create', JobOffer::class) ? route('recruitment.offers.store', [], false) : null,
            'offers_release_url_template' => '/recruitment/offers/__OFFER__/release',
            'current_user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'can_view_job_openings' => true,
            'can_create_job_opening' => $user->can('create', JobOpening::class),
            'can_approve_job_openings' => $user->hasPermission('recruitment.approve'),
            'can_view_candidates' => $user->can('viewAny', Candidate::class),
            'can_create_candidate' => $user->can('create', Candidate::class),
            'can_update_candidate_stage' => $user->hasPermission('recruitment.manage'),
            'can_convert_candidates' => $user->hasPermission('recruitment.approve'),
            'can_schedule_interview' => $user->can('create', Candidate::class),
            'can_view_offers' => $user->can('viewAny', JobOffer::class),
            'can_create_offer' => $user->can('create', JobOffer::class),
            'can_release_offers' => $user->hasPermission('recruitment.approve'),
            'offer_templates' => [
                ['value' => 'offer_letter_v4', 'label' => 'Offer Letter v4'],
                ['value' => 'appointment_letter_v2', 'label' => 'Appointment Letter v2'],
            ],
            'companies' => $companies,
            'branches' => $branches,
            'projects' => $projects,
            'employment_types' => [
                ['value' => 'full_time', 'label' => 'Full-time'],
                ['value' => 'part_time', 'label' => 'Part-time'],
                ['value' => 'contract', 'label' => 'Contract'],
                ['value' => 'intern', 'label' => 'Intern'],
                ['value' => 'consultant', 'label' => 'Consultant'],
            ],
            'candidate_stages' => [
                ['value' => 'screening', 'label' => 'Screening'],
                ['value' => 'interview_scheduled', 'label' => 'Interview Scheduled'],
                ['value' => 'selected', 'label' => 'Selected'],
                ['value' => 'offer_draft', 'label' => 'Offer Draft'],
                ['value' => 'offer_released', 'label' => 'Offer Released'],
                ['value' => 'employee_created', 'label' => 'Employee Created'],
                ['value' => 'rejected', 'label' => 'Rejected'],
            ],
            'candidate_sources' => (clone $candidateQuery)
                ->select('source')
                ->whereNotNull('source')
                ->distinct()
                ->orderBy('source')
                ->pluck('source')
                ->values()
                ->all(),
            'panel_users' => User::query()
                ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
                ->where('status', 'active')
                ->orderBy('name')
                ->limit(80)
                ->get(['id', 'name', 'email', 'company_id'])
                ->map(fn (User $panelUser): array => [
                    'id' => $panelUser->id,
                    'name' => $panelUser->name,
                    'email' => $panelUser->email,
                    'company_id' => $panelUser->company_id,
                    'label' => $panelUser->name.' · '.$panelUser->email,
                ])
                ->values()
                ->all(),
            'departments' => ['Sales', 'Construction', 'Finance', 'HR', 'Legal', 'Customer Service'],
            'job_openings' => $openings,
            'summary' => [
                'open_positions' => (int) (clone $openingQuery)->whereIn('status', ['pending_approval', 'open'])->sum('positions'),
                'pending_approval' => (int) ($statusCounts['pending_approval'] ?? 0),
                'open_requisitions' => (int) ($statusCounts['open'] ?? 0),
                'candidates' => (int) $candidateQuery->count(),
                'interviews' => (int) $interviewQuery->whereIn('status', ['scheduled', 'completed'])->count(),
                'offers_pending' => (int) $offerQuery->whereIn('status', ['draft', 'pending_approval', 'released'])->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function jobOpeningBootstrapRow(JobOpening $opening, User $user): array
    {
        return [
            'id' => $opening->id,
            'opening_code' => $opening->opening_code,
            'title' => $opening->title,
            'department' => $opening->department,
            'designation' => $opening->designation,
            'positions' => (int) $opening->positions,
            'employment_type' => $opening->employment_type,
            'work_location' => $opening->work_location,
            'budget_min_ctc' => (float) $opening->budget_min_ctc,
            'budget_max_ctc' => (float) $opening->budget_max_ctc,
            'status' => $opening->status,
            'target_hiring_date' => $opening->target_hiring_date?->toDateString(),
            'required_skills' => $opening->required_skills ?? [],
            'business_justification' => $opening->metadata['business_justification'] ?? null,
            'workflow_history' => $opening->metadata['workflow_history'] ?? [],
            'can_approve' => $user->can('approve', $opening),
            'company' => $opening->company ? [
                'id' => $opening->company->id,
                'code' => $opening->company->code,
                'name' => $opening->company->name,
            ] : null,
            'branch' => $opening->branch ? [
                'id' => $opening->branch->id,
                'code' => $opening->branch->code,
                'name' => $opening->branch->name,
            ] : null,
            'project' => $opening->project ? [
                'id' => $opening->project->id,
                'code' => $opening->project->code,
                'name' => $opening->project->name,
            ] : null,
            'created_by' => $opening->createdBy ? [
                'id' => $opening->createdBy->id,
                'name' => $opening->createdBy->name,
                'email' => $opening->createdBy->email,
            ] : null,
            'reviewed_by' => $opening->reviewedBy ? [
                'id' => $opening->reviewedBy->id,
                'name' => $opening->reviewedBy->name,
                'email' => $opening->reviewedBy->email,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function hrPerformanceOptions(?User $user): ?array
    {
        if (! $user || $this->isPartnerPortalUser($user) || $this->isBuyerPortalUser($user)) {
            return null;
        }

        $canViewCycles = $user->can('viewAny', PerformanceCycle::class);
        $canCreateCycle = $user->can('create', PerformanceCycle::class);
        $canViewReviews = $user->can('viewAny', PerformanceReview::class);
        $canCreateReview = $user->can('create', PerformanceReview::class);

        if (! $canViewCycles && ! $canCreateCycle && ! $canViewReviews && ! $canCreateReview) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);

        $employees = Employee::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(150)
            ->get(['id', 'company_id', 'employee_code', 'name', 'designation', 'department', 'manager_employee_id'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'company_id' => $employee->company_id,
                'employee_code' => $employee->employee_code,
                'name' => $employee->name,
                'label' => $employee->employee_code.' · '.$employee->name,
                'designation' => $employee->designation,
                'department' => $employee->department,
                'manager_employee_id' => $employee->manager_employee_id,
            ])
            ->values()
            ->all();

        $departments = Employee::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->whereNotNull('department')
            ->distinct()
            ->orderBy('department')
            ->pluck('department')
            ->values()
            ->all();

        return [
            'source' => 'laravel-sqlite',
            'performance_cycles_index_url' => $canViewCycles ? route('hr.performance-cycles.index', [], false) : null,
            'performance_cycles_store_url' => $canCreateCycle ? route('hr.performance-cycles.store', [], false) : null,
            'performance_reviews_index_url' => $canViewReviews ? route('hr.performance-reviews.index', [], false) : null,
            'performance_reviews_store_url' => $canCreateReview ? route('hr.performance-reviews.store', [], false) : null,
            'performance_review_manager_submit_url_template' => '/hr/performance-reviews/__REVIEW__/manager-submit',
            'performance_review_close_url_template' => '/hr/performance-reviews/__REVIEW__/close',
            'can_view_performance_cycles' => $canViewCycles,
            'can_create_performance_cycle' => $canCreateCycle,
            'can_view_performance_reviews' => $canViewReviews,
            'can_create_performance_review' => $canCreateReview,
            'can_submit_manager_review' => $user->hasPermission('performance.manage') || $user->hasPermission('performance.approve') || $user->hasPermission('*'),
            'can_close_performance_review' => $user->hasPermission('performance.approve') || $user->hasPermission('*'),
            'companies' => $this->companyRows($user),
            'projects' => $this->projectRows($user),
            'employees' => $employees,
            'departments' => $departments,
            'frequencies' => [
                ['value' => 'monthly', 'label' => 'Monthly'],
                ['value' => 'quarterly', 'label' => 'Quarterly'],
                ['value' => 'annual', 'label' => 'Annual'],
            ],
            'cycle_statuses' => [
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'active', 'label' => 'Active'],
            ],
            'review_statuses' => [
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'self_submitted', 'label' => 'Self Submitted'],
                ['value' => 'manager_submitted', 'label' => 'Manager Submitted'],
                ['value' => 'closed', 'label' => 'Closed'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function hrLifecycleOptions(?User $user): ?array
    {
        if (! $user || $this->isPartnerPortalUser($user) || $this->isBuyerPortalUser($user)) {
            return null;
        }

        $canViewConfirmations = $user->can('viewAny', EmployeeConfirmationCase::class);
        $canCreateConfirmations = $user->can('create', EmployeeConfirmationCase::class);
        $canViewSettlements = $user->can('viewAny', EmployeeSeparationSettlement::class);
        $canCreateSettlements = $user->can('create', EmployeeSeparationSettlement::class);
        $canViewExitInterviews = $user->can('viewAny', EmployeeExitInterview::class);
        $canCreateExitInterviews = $user->can('create', EmployeeExitInterview::class);
        $canViewExitSummary = $user->hasPermission('hr.view') || $user->hasPermission('hr.manage') || $user->hasPermission('*');

        if (! $canViewConfirmations && ! $canViewSettlements && ! $canViewExitInterviews && ! $canViewExitSummary) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);
        $employees = Employee::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->whereIn('status', ['active', 'probation', 'on_notice'])
            ->orderBy('name')
            ->limit(150)
            ->get(['id', 'company_id', 'employee_code', 'name', 'designation', 'department', 'manager_employee_id', 'status'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'company_id' => $employee->company_id,
                'employee_code' => $employee->employee_code,
                'name' => $employee->name,
                'label' => $employee->employee_code.' · '.$employee->name,
                'designation' => $employee->designation,
                'department' => $employee->department,
                'manager_employee_id' => $employee->manager_employee_id,
                'status' => $employee->status,
            ])
            ->values()
            ->all();

        return [
            'source' => 'laravel-sqlite',
            'confirmation_cases_index_url' => $canViewConfirmations ? route('hr.confirmation-cases.index', [], false) : null,
            'confirmation_cases_store_url' => $canCreateConfirmations ? route('hr.confirmation-cases.store', [], false) : null,
            'confirmation_case_recommend_url_template' => '/hr/confirmation-cases/__CASE__/recommend',
            'confirmation_case_decide_url_template' => '/hr/confirmation-cases/__CASE__/decide',
            'separation_settlements_index_url' => $canViewSettlements ? route('hr.separation-settlements.index', [], false) : null,
            'separation_settlements_store_url' => $canCreateSettlements ? route('hr.separation-settlements.store', [], false) : null,
            'separation_settlement_hr_approve_url_template' => '/hr/separation-settlements/__SETTLEMENT__/hr-approve',
            'separation_settlement_finance_approve_url_template' => '/hr/separation-settlements/__SETTLEMENT__/finance-approve',
            'separation_settlement_complete_url_template' => '/hr/separation-settlements/__SETTLEMENT__/complete',
            'exit_interviews_index_url' => $canViewExitInterviews ? route('hr.exit-interviews.index', [], false) : null,
            'exit_interviews_store_url' => $canCreateExitInterviews ? route('hr.exit-interviews.store', [], false) : null,
            'exit_interviews_summary_url' => $canViewExitSummary ? route('hr.exit-interviews.summary', [], false) : null,
            'exit_interview_submit_url_template' => '/hr/exit-interviews/__INTERVIEW__/submit',
            'exit_interview_review_url_template' => '/hr/exit-interviews/__INTERVIEW__/review',
            'can_view_confirmations' => $canViewConfirmations,
            'can_create_confirmation' => $canCreateConfirmations,
            'can_recommend_confirmation' => $user->hasPermission('performance.manage'),
            'can_decide_confirmation' => $user->hasPermission('hr.manage') || $user->hasPermission('*'),
            'can_view_separation_settlements' => $canViewSettlements,
            'can_create_separation_settlement' => $canCreateSettlements,
            'can_hr_approve_separation_settlement' => $user->hasPermission('hr.manage') || $user->hasPermission('*'),
            'can_finance_approve_separation_settlement' => $user->hasPermission('finance.approve') || $user->hasPermission('*'),
            'can_view_exit_interviews' => $canViewExitInterviews,
            'can_create_exit_interview' => $canCreateExitInterviews,
            'can_submit_exit_interview' => $user->hasPermission('employee.self_service') || $user->hasPermission('hr.manage') || $user->hasPermission('*'),
            'can_review_exit_interview' => $user->hasPermission('hr.manage') || $user->hasPermission('*'),
            'employees' => $employees,
            'separation_types' => [
                ['value' => 'resignation', 'label' => 'Resignation'],
                ['value' => 'termination', 'label' => 'Termination'],
                ['value' => 'retirement', 'label' => 'Retirement'],
                ['value' => 'contract_end', 'label' => 'Contract End'],
            ],
            'exit_reasons' => [
                ['value' => 'career_growth', 'label' => 'Career Growth'],
                ['value' => 'compensation', 'label' => 'Compensation'],
                ['value' => 'relocation', 'label' => 'Relocation'],
                ['value' => 'manager_issue', 'label' => 'Manager Issue'],
                ['value' => 'work_environment', 'label' => 'Work Environment'],
                ['value' => 'personal', 'label' => 'Personal'],
                ['value' => 'other', 'label' => 'Other'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function hrPayrollOptions(?User $user): ?array
    {
        if (! $user || $this->isPartnerPortalUser($user) || $this->isBuyerPortalUser($user)) {
            return null;
        }

        $canViewRuns = $user->can('viewAny', PayrollRun::class);
        $canCreateRun = $user->can('create', PayrollRun::class);
        $canViewBankBatches = $user->can('viewAny', PayrollBankTransferBatch::class);
        $canViewCommissionRules = $user->can('viewAny', CommissionRule::class);
        $canCreateCommissionRule = $user->can('create', CommissionRule::class);
        $canViewCommissionRuns = $user->can('viewAny', CommissionRun::class);
        $canCreateCommissionRun = $user->can('create', CommissionRun::class);
        $canViewTaxDocuments = $user->can('viewAny', EmployeeTaxDocument::class);
        $canCreateTaxDocument = $user->can('create', EmployeeTaxDocument::class);
        $canManagePayroll = $user->hasPermission('payroll.manage') || $user->hasPermission('*');
        $canApprovePayroll = $user->hasPermission('payroll.approve') || $user->hasPermission('*');

        if (! $canViewRuns && ! $canViewBankBatches && ! $canViewCommissionRules && ! $canViewCommissionRuns && ! $canViewTaxDocuments) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);
        $employees = Employee::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->whereIn('status', ['active', 'probation', 'on_notice'])
            ->orderBy('name')
            ->limit(150)
            ->get(['id', 'company_id', 'employee_code', 'name', 'designation', 'department', 'status'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'company_id' => $employee->company_id,
                'employee_code' => $employee->employee_code,
                'name' => $employee->name,
                'label' => $employee->employee_code.' · '.$employee->name,
                'designation' => $employee->designation,
                'department' => $employee->department,
                'status' => $employee->status,
            ])
            ->values()
            ->all();

        return [
            'source' => 'laravel-sqlite',
            'components_index_url' => $canViewRuns ? route('payroll.components.index', [], false) : null,
            'salary_structures_index_url' => $canViewRuns ? route('payroll.salary-structures.index', [], false) : null,
            'payroll_runs_index_url' => $canViewRuns ? route('payroll.runs.index', [], false) : null,
            'payroll_runs_generate_url' => $canCreateRun ? route('payroll.runs.generate', [], false) : null,
            'payroll_run_approve_url_template' => '/payroll/runs/__RUN__/approve',
            'bank_transfer_batches_index_url' => $canViewBankBatches ? route('payroll.bank-transfer-batches.index', [], false) : null,
            'bank_transfer_batch_prepare_url_template' => '/payroll/runs/__RUN__/bank-transfer-batches',
            'bank_transfer_batch_release_url_template' => '/payroll/bank-transfer-batches/__BATCH__/release',
            'tax_documents_index_url' => $canViewTaxDocuments ? route('payroll.tax-documents.index', [], false) : null,
            'tax_documents_store_url' => $canCreateTaxDocument ? route('payroll.tax-documents.store', [], false) : null,
            'tax_document_issue_url_template' => '/payroll/tax-documents/__DOCUMENT__/issue',
            'tax_document_acknowledge_url_template' => '/payroll/tax-documents/__DOCUMENT__/acknowledge',
            'commission_rules_index_url' => $canViewCommissionRules ? route('payroll.commission-rules.index', [], false) : null,
            'commission_rules_store_url' => $canCreateCommissionRule ? route('payroll.commission-rules.store', [], false) : null,
            'commission_runs_index_url' => $canViewCommissionRuns ? route('payroll.commission-runs.index', [], false) : null,
            'commission_runs_store_url' => $canCreateCommissionRun ? route('payroll.commission-runs.store', [], false) : null,
            'commission_run_approve_url_template' => '/payroll/commission-runs/__RUN__/approve',
            'commission_run_reject_url_template' => '/payroll/commission-runs/__RUN__/reject',
            'can_view_payroll_runs' => $canViewRuns,
            'can_generate_payroll_run' => $canCreateRun,
            'can_approve_payroll_run' => $canApprovePayroll,
            'can_view_bank_transfer_batches' => $canViewBankBatches,
            'can_prepare_bank_transfer_batch' => $canManagePayroll,
            'can_release_bank_transfer_batch' => $canApprovePayroll,
            'can_view_commission_rules' => $canViewCommissionRules,
            'can_create_commission_rule' => $canCreateCommissionRule,
            'can_view_commission_runs' => $canViewCommissionRuns,
            'can_create_commission_run' => $canCreateCommissionRun,
            'can_approve_commission_run' => $canApprovePayroll,
            'can_view_tax_documents' => $canViewTaxDocuments,
            'can_generate_tax_document' => $canCreateTaxDocument,
            'can_issue_tax_document' => $canApprovePayroll || $user->hasPermission('compliance.manage'),
            'employees' => $employees,
            'default_bank_batch' => [
                'bank_name' => 'HDFC Bank',
                'debit_account_number' => '1234567890',
                'narration' => 'Builder360 salary transfer',
            ],
            'payroll_statuses' => [
                ['value' => 'generated', 'label' => 'Generated'],
                ['value' => 'approved', 'label' => 'Approved'],
            ],
            'bank_batch_statuses' => [
                ['value' => 'prepared', 'label' => 'Prepared'],
                ['value' => 'released', 'label' => 'Released'],
            ],
            'commission_run_statuses' => [
                ['value' => 'generated', 'label' => 'Generated'],
                ['value' => 'approved', 'label' => 'Approved'],
                ['value' => 'rejected', 'label' => 'Rejected'],
            ],
            'tax_document_statuses' => [
                ['value' => 'generated', 'label' => 'Generated'],
                ['value' => 'issued', 'label' => 'Issued'],
                ['value' => 'acknowledged', 'label' => 'Acknowledged'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function hrComplianceOptions(?User $user): ?array
    {
        if (! $user || $this->isPartnerPortalUser($user) || $this->isBuyerPortalUser($user)) {
            return null;
        }

        $canView = $user->hasPermission('compliance.view')
            || $user->hasPermission('compliance.manage')
            || $user->can('viewAny', SystemSetting::class);

        if (! $canView) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);
        $settingKeys = [
            'payroll.tax_rules',
            'finance.gst_rules',
            'hr.statutory.pf',
            'hr.statutory.esic',
            'hr.statutory.professional_tax',
            'hr.statutory.labour_welfare_fund',
            'hr.statutory.gratuity_bonus',
            'hr.leave.rules',
        ];

        $settingQuery = SystemSetting::query()
            ->with(['company:id,code,name', 'createdBy:id,name,email', 'approvedBy:id,name,email'])
            ->whereIn('setting_key', $settingKeys)
            ->when(is_array($companyIds), function (Builder $query) use ($companyIds): void {
                $query->where(function (Builder $scope) use ($companyIds): void {
                    $scope->whereNull('company_id')
                        ->orWhereIn('company_id', $companyIds ?: [0]);
                });
            });

        $settings = (clone $settingQuery)
            ->orderByRaw("case when status = 'draft' then 0 when status = 'active' then 1 else 2 end")
            ->orderBy('setting_key')
            ->orderByDesc('version')
            ->limit(80)
            ->get()
            ->map(fn (SystemSetting $setting): array => $this->systemSettingBootstrapRow($setting))
            ->values()
            ->all();

        return [
            'source' => 'laravel-sqlite',
            'index_url' => route('hr.compliance-rule-settings.index', [], false),
            'store_url' => route('hr.compliance-rule-settings.store', [], false),
            'approve_url_template' => '/hr/compliance-rule-settings/__SETTING__/approve',
            'can_view' => $canView,
            'can_create' => $user->hasPermission('compliance.manage') || $user->hasPermission('settings.manage'),
            'can_approve' => $user->hasPermission('compliance.manage') || $user->hasPermission('settings.approve'),
            'settings' => $settings,
            'setting_keys' => array_map(fn (string $key): array => [
                'value' => $key,
                'label' => str($key)->replace('.', ' ')->replace('_', ' ')->title()->toString(),
            ], $settingKeys),
            'presets' => $this->hrComplianceRulePresets(),
            'summary' => [
                'active' => (clone $settingQuery)->where('status', 'active')->count(),
                'draft' => (clone $settingQuery)->where('status', 'draft')->count(),
                'archived' => (clone $settingQuery)->where('status', 'archived')->count(),
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function hrComplianceRulePresets(): array
    {
        return [
            'payroll.tax_rules' => [
                'financial_year' => now()->format('Y').'-'.now()->addYear()->format('Y'),
                'payroll_year_locked' => false,
                'verified' => false,
                'statutory_validation_required' => true,
                'form16_template_version' => 'draft-v1',
                'standard_deduction' => 50000,
                'approval_chain' => ['compliance_preparer', 'compliance_approver'],
                'prototype_notice' => 'Client-appointed tax expert must confirm tax rules before statutory use.',
            ],
            'finance.gst_rules' => [
                'supported_transaction_types' => ['output', 'input', 'reverse_charge', 'adjustment'],
                'default_tax_rates' => [0, 5, 12, 18, 28],
                'return_frequency' => 'monthly',
                'verified' => false,
                'statutory_validation_required' => true,
                'approval_chain' => ['finance_preparer', 'compliance_approver', 'period_lock'],
                'prototype_notice' => 'GST records are tracking registers until client tax review is completed.',
            ],
            'hr.leave.rules' => [
                'monthly_accrual_enabled' => true,
                'year_end_processing_enabled' => true,
                'encashment_tax_rate' => 0.10,
                'encashment_formula' => 'approved_days * monthly_ctc / 30 - configured_tax',
                'verified' => false,
                'statutory_validation_required' => true,
                'approval_chain' => ['hr_preparer', 'compliance_approver'],
            ],
            'default_hr_statutory' => [
                'applicability' => ['employee_categories' => ['full_time'], 'states' => ['MH']],
                'wage_basis' => 'configured_wage_components',
                'calculation_method' => 'configured_formula',
                'rates' => ['employee' => 0, 'employer' => 0, 'ceiling' => null],
                'rounding' => 'nearest_rupee',
                'verified' => false,
                'statutory_validation_required' => true,
                'approval_chain' => ['hr_preparer', 'compliance_approver'],
                'prototype_notice' => 'Rates and applicability require client-appointed statutory expert confirmation.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function hrOperationsOptions(?User $user): ?array
    {
        if (! $user || $this->isPartnerPortalUser($user) || $this->isBuyerPortalUser($user)) {
            return null;
        }

        $hasInternalOperationsAccess = $user->hasPermission('*')
            || $user->hasPermission('hr.manage')
            || $user->hasPermission('claims.view')
            || $user->hasPermission('claims.manage')
            || $user->hasPermission('claims.approve')
            || $user->hasPermission('assets.view')
            || $user->hasPermission('assets.manage')
            || $user->hasPermission('documents.view')
            || $user->hasPermission('documents.manage')
            || $user->hasPermission('documents.approve')
            || $user->hasPermission('loans.view')
            || $user->hasPermission('loans.manage')
            || $user->hasPermission('loans.approve')
            || $user->hasPermission('helpdesk.view')
            || $user->hasPermission('helpdesk.manage')
            || $user->hasPermission('finance.approve');

        if (! $hasInternalOperationsAccess) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);

        $canViewAssets = $user->can('viewAny', EmployeeAsset::class);
        $canViewEmployeeDocuments = $user->can('viewAny', Employee::class)
            && (
                $user->hasPermission('*')
                || $user->hasPermission('hr.manage')
                || $user->hasPermission('documents.view')
                || $user->hasPermission('documents.manage')
                || $user->hasPermission('documents.approve')
            );
        $canApproveEmployeeDocuments = $user->hasPermission('documents.approve');
        $canViewClaims = $user->can('viewAny', ExpenseClaim::class);
        $canViewLoans = $user->can('viewAny', EmployeeLoan::class);
        $canViewHelpdesk = $user->can('viewAny', HrHelpdeskTicket::class);
        $canManageAssets = $user->hasPermission('assets.manage') || $user->hasPermission('hr.manage');
        $canManageHelpdesk = $user->hasPermission('helpdesk.manage') || $user->hasPermission('hr.manage');
        $canApproveClaims = $user->hasPermission('claims.approve') || $user->hasPermission('hr.manage');
        $canPayClaims = $user->hasPermission('finance.approve');
        $canApproveLoans = $user->hasPermission('loans.approve') || $user->hasPermission('hr.manage');
        $canDisburseLoans = $user->hasPermission('finance.approve');

        if (! $canViewAssets && ! $canViewEmployeeDocuments && ! $canViewClaims && ! $canViewLoans && ! $canViewHelpdesk) {
            return null;
        }

        $helpdeskAssignees = $canViewHelpdesk ? User::query()
            ->with('role:id,name,slug,permissions')
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->where('status', 'active')
            ->whereNotNull('company_id')
            ->orderBy('name')
            ->limit(80)
            ->get(['id', 'company_id', 'role_id', 'name', 'email'])
            ->filter(function (User $assignee): bool {
                $permissions = $assignee->role?->permissions ?? [];

                return in_array('helpdesk.manage', $permissions, true)
                    || in_array('hr.manage', $permissions, true);
            })
            ->map(fn (User $assignee): array => [
                'id' => $assignee->id,
                'name' => $assignee->name,
                'email' => $assignee->email,
                'role' => $assignee->role?->name,
                'label' => $assignee->name.' · '.$assignee->email,
            ])
            ->values()
            ->all() : [];

        $assetAssignableEmployees = $canManageAssets ? Employee::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->whereIn('status', ['active', 'probation', 'on_notice'])
            ->orderBy('employee_code')
            ->limit(100)
            ->get(['id', 'company_id', 'employee_code', 'name', 'designation', 'department', 'status'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'company_id' => $employee->company_id,
                'employee_code' => $employee->employee_code,
                'name' => $employee->name,
                'designation' => $employee->designation,
                'department' => $employee->department,
                'status' => $employee->status,
                'label' => $employee->employee_code.' · '.$employee->name.' · '.$employee->department,
            ])
            ->values()
            ->all() : [];

        $helpdeskRequestEmployees = ($canViewHelpdesk && $user->can('create', HrHelpdeskTicket::class)) ? Employee::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->whereIn('status', ['active', 'probation', 'on_notice'])
            ->orderBy('employee_code')
            ->limit(120)
            ->get(['id', 'company_id', 'employee_code', 'name', 'designation', 'department', 'status'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'company_id' => $employee->company_id,
                'employee_code' => $employee->employee_code,
                'name' => $employee->name,
                'designation' => $employee->designation,
                'department' => $employee->department,
                'status' => $employee->status,
                'label' => $employee->employee_code.' · '.$employee->name.' · '.$employee->department,
            ])
            ->values()
            ->all() : [];

        return [
            'source' => 'laravel-sqlite',
            'assets_index_url' => $canViewAssets ? route('hr.assets.index', [], false) : null,
            'assets_store_url' => $user->can('create', EmployeeAsset::class) ? route('hr.assets.store', [], false) : null,
            'asset_assign_url_template' => $canManageAssets ? '/hr/assets/__ASSET__/assign' : null,
            'asset_recover_url_template' => $canManageAssets ? '/hr/assets/__ASSET__/recover' : null,
            'employee_documents_index_url' => $canViewEmployeeDocuments ? route('hr.employee-documents.index', [], false) : null,
            'employee_document_approve_url_template' => $canApproveEmployeeDocuments ? '/hr/employees/__EMPLOYEE__/documents/__DOCUMENT__/approve' : null,
            'claims_index_url' => $canViewClaims ? route('hr.expense-claims.index', [], false) : null,
            'claim_approve_url_template' => '/hr/expense-claims/__CLAIM__/approve',
            'claim_reject_url_template' => '/hr/expense-claims/__CLAIM__/reject',
            'claim_pay_url_template' => '/hr/expense-claims/__CLAIM__/pay',
            'loans_index_url' => $canViewLoans ? route('hr.loans.index', [], false) : null,
            'loan_approve_url_template' => '/hr/loans/__LOAN__/approve',
            'loan_reject_url_template' => '/hr/loans/__LOAN__/reject',
            'loan_disburse_url_template' => '/hr/loans/__LOAN__/disburse',
            'helpdesk_index_url' => $canViewHelpdesk ? route('hr.helpdesk-tickets.index', [], false) : null,
            'helpdesk_store_url' => ($canViewHelpdesk && $user->can('create', HrHelpdeskTicket::class)) ? route('hr.helpdesk-tickets.store', [], false) : null,
            'helpdesk_assign_url_template' => '/hr/helpdesk-tickets/__TICKET__/assign',
            'helpdesk_resolve_url_template' => '/hr/helpdesk-tickets/__TICKET__/resolve',
            'helpdesk_close_url_template' => '/hr/helpdesk-tickets/__TICKET__/close',
            'can_view_assets' => $canViewAssets,
            'can_create_assets' => $user->can('create', EmployeeAsset::class),
            'can_assign_assets' => $canManageAssets,
            'can_recover_assets' => $canManageAssets,
            'can_view_employee_documents' => $canViewEmployeeDocuments,
            'can_approve_employee_documents' => $canApproveEmployeeDocuments,
            'can_view_claims' => $canViewClaims,
            'can_view_loans' => $canViewLoans,
            'can_view_helpdesk' => $canViewHelpdesk,
            'can_create_helpdesk' => $canViewHelpdesk && $user->can('create', HrHelpdeskTicket::class),
            'can_approve_claims' => $canApproveClaims,
            'can_pay_claims' => $canPayClaims,
            'can_approve_loans' => $canApproveLoans,
            'can_disburse_loans' => $canDisburseLoans,
            'can_assign_helpdesk' => $canManageHelpdesk,
            'can_resolve_helpdesk' => $canManageHelpdesk,
            'can_close_helpdesk' => $canManageHelpdesk,
            'asset_statuses' => ['available', 'assigned', 'recovered', 'retired', 'lost'],
            'asset_categories' => ['laptop', 'mobile', 'sim', 'access_card', 'vehicle', 'tool', 'other'],
            'employee_document_statuses' => ['submitted', 'approved', 'rejected', 'archived'],
            'claim_statuses' => ['submitted', 'approved', 'rejected', 'paid'],
            'loan_statuses' => ['submitted', 'approved', 'rejected', 'disbursed', 'closed'],
            'helpdesk_statuses' => ['open', 'assigned', 'resolved', 'closed'],
            'asset_assignable_employees' => $assetAssignableEmployees,
            'helpdesk_assignees' => $helpdeskAssignees,
            'helpdesk_request_employees' => $helpdeskRequestEmployees,
            'companies' => $this->companyRows($user),
            'claim_types' => ['travel', 'fuel', 'food', 'medical', 'office', 'other'],
            'loan_types' => ['salary_advance', 'emergency', 'welfare', 'other'],
            'helpdesk_categories' => ['payroll', 'attendance', 'leave', 'documents', 'assets', 'policy', 'other'],
            'priorities' => ['low', 'medium', 'high', 'critical'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function hrEmployeeOptions(?User $user): ?array
    {
        if (! $user || ! $user->can('viewAny', Employee::class)) {
            return null;
        }

        if ($this->isPartnerPortalUser($user) || $this->isBuyerPortalUser($user)) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);

        $companies = Company::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('id', $companyIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'state'])
            ->map(fn (Company $company): array => [
                'id' => $company->id,
                'code' => $company->code,
                'name' => $company->name,
                'state' => $company->state,
                'label' => $company->code.' · '.$company->name,
            ])
            ->values()
            ->all();

        $branches = Branch::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'company_id', 'code', 'name', 'state'])
            ->map(fn (Branch $branch): array => [
                'id' => $branch->id,
                'company_id' => $branch->company_id,
                'code' => $branch->code,
                'name' => $branch->name,
                'state' => $branch->state,
                'label' => $branch->code.' · '.$branch->name,
            ])
            ->values()
            ->all();

        $projects = Project::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'company_id', 'branch_id', 'code', 'name', 'state'])
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'company_id' => $project->company_id,
                'branch_id' => $project->branch_id,
                'code' => $project->code,
                'name' => $project->name,
                'state' => $project->state,
                'label' => $project->code.' · '.$project->name,
            ])
            ->values()
            ->all();

        $managers = Employee::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'company_id', 'employee_code', 'name', 'designation', 'department'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'company_id' => $employee->company_id,
                'employee_code' => $employee->employee_code,
                'name' => $employee->name,
                'designation' => $employee->designation,
                'department' => $employee->department,
                'label' => $employee->employee_code.' · '.$employee->name.' · '.$employee->designation,
            ])
            ->values()
            ->all();

        $employeeDocumentCategories = DocumentCategory::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->where(function (Builder $scope) use ($companyIds): void {
                $scope->whereNull('company_id')
                    ->orWhereIn('company_id', $companyIds ?: [0]);
            }))
            ->where('is_active', true)
            ->whereIn('owner_type', ['employee', 'global'])
            ->orderBy('code')
            ->get(['id', 'company_id', 'code', 'name', 'owner_type', 'expiry_required', 'reminder_days_before_expiry', 'retention_years', 'is_active'])
            ->map(fn (DocumentCategory $category): array => [
                'id' => $category->id,
                'company_id' => $category->company_id,
                'code' => $category->code,
                'name' => $category->name,
                'owner_type' => $category->owner_type,
                'expiry_required' => $category->expiry_required,
                'reminder_days_before_expiry' => $category->reminder_days_before_expiry,
                'retention_years' => $category->retention_years,
                'label' => $category->code.' · '.$category->name,
            ])
            ->values()
            ->all();

        return [
            'source' => 'laravel-sqlite',
            'index_url' => route('hr.employees.index', [], false),
            'show_url_template' => '/hr/employees/__EMPLOYEE__',
            'profile_sections_url_template' => '/hr/employees/__EMPLOYEE__/profile-sections',
            'movements_url_template' => '/hr/employees/__EMPLOYEE__/movements',
            'movement_approve_url_template' => '/hr/employees/__EMPLOYEE__/movements/__MOVEMENT__/approve',
            'documents_url_template' => '/hr/employees/__EMPLOYEE__/documents',
            'document_approve_url_template' => '/hr/employees/__EMPLOYEE__/documents/__DOCUMENT__/approve',
            'payroll_summary_url_template' => '/hr/employees/__EMPLOYEE__/payroll-summary',
            'audit_events_url_template' => '/hr/employees/__EMPLOYEE__/audit-events',
            'attendance_records_url' => route('hr.attendance-records.index', [], false),
            'leave_balances_url' => route('hr.leave-balances.index', [], false),
            'leave_requests_url' => route('hr.leave-requests.index', [], false),
            'assets_url' => route('hr.assets.index', [], false),
            'store_url' => route('hr.employees.store', [], false),
            'can_create' => $user->can('create', Employee::class),
            'can_import' => $user->can('create', DataImportBatch::class) && $user->can('create', Employee::class),
            'import_type' => DataImportBatch::TYPE_HR_EMPLOYEES,
            'import_preview_url' => route('settings.data-imports.preview', [], false),
            'import_post_url_template' => '/settings/data-imports/__BATCH__/post',
            'import_requires_company_selection' => $user->hasPermission('*'),
            'import_required_headers' => [
                'employee_code',
                'name',
                'designation',
                'department',
                'grade',
                'employment_type',
                'status',
                'joined_on',
                'statutory_state',
                'branch_code',
                'project_code',
                'manager_employee_code',
                'monthly_ctc',
                'pan',
                'aadhaar',
                'uan',
                'bank_account',
            ],
            'import_sample_csv' => 'employee_code,name,designation,department,grade,employment_type,status,joined_on,statutory_state,branch_code,project_code,manager_employee_code,monthly_ctc,pan,aadhaar,uan,bank_account'."\n"
                .'EMP-IMPORT-001,Sample Employee,Site Engineer,Construction,B1,full_time,active,'.now()->subMonth()->toDateString().',MH,,,,65000,ABCDE1234F,123412341234,100200300400,123456789012',
            'import_max_file_size_kb' => 512,
            'can_create_movement' => $user->hasPermission('hr.manage'),
            'can_create_employee_document' => $user->hasPermission('hr.manage'),
            'can_update_profile_sections' => $user->hasPermission('hr.manage'),
            'can_view_attendance_records' => $user->can('viewAny', AttendanceRegularizationRequest::class),
            'can_view_leave_records' => $user->can('viewAny', LeaveRequest::class),
            'can_view_asset_records' => $user->can('viewAny', EmployeeAsset::class),
            'can_view_payroll_records' => $user->hasPermission('*')
                || $user->hasPermission('hr.manage')
                || $user->hasPermission('payroll.view')
                || $user->hasPermission('payroll.manage')
                || $user->hasPermission('payroll.approve'),
            'can_view_employee_audit_events' => $user->hasPermission('*')
                || $user->hasPermission('audit.view')
                || $user->hasPermission('hr.manage'),
            'companies' => $companies,
            'branches' => $branches,
            'projects' => $projects,
            'managers' => $managers,
            'employee_document_categories' => $employeeDocumentCategories,
            'departments' => ['Construction', 'Sales', 'Finance', 'HR', 'IT', 'Customer Success', 'Procurement', 'Legal'],
            'employment_types' => [
                ['value' => 'full_time', 'label' => 'Full-time'],
                ['value' => 'part_time', 'label' => 'Part-time'],
                ['value' => 'contract', 'label' => 'Contract'],
                ['value' => 'intern', 'label' => 'Intern'],
                ['value' => 'consultant', 'label' => 'Consultant'],
            ],
            'statuses' => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
                ['value' => 'on_notice', 'label' => 'On Notice'],
                ['value' => 'separated', 'label' => 'Separated'],
            ],
            'grades' => ['A', 'B1', 'B2', 'C', 'M1', 'M2'],
            'next_employee_code_hint' => 'EMP-'.now()->format('ymdHis'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function hrReportOptions(?User $user): ?array
    {
        if (! $user || ! $user->can('viewAny', Employee::class)) {
            return null;
        }

        if ($this->isPartnerPortalUser($user) || $this->isBuyerPortalUser($user)) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);

        $employeeQuery = Employee::query()
            ->with('company:id,code,name')
            ->when(is_array($companyIds), fn (Builder $query): Builder => $query->whereIn('company_id', $companyIds ?: [0]));

        $companyFilters = Company::query()
            ->when(is_array($companyIds), fn (Builder $query): Builder => $query->whereIn('id', $companyIds ?: [0]))
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Company $company): array => [
                'value' => $company->id,
                'label' => $company->code.' · '.$company->name,
            ])
            ->values()
            ->all();

        $departmentFilters = (clone $employeeQuery)
            ->whereNotNull('department')
            ->select('department')
            ->distinct()
            ->orderBy('department')
            ->pluck('department')
            ->map(fn (string $department): array => [
                'value' => $department,
                'label' => $department,
            ])
            ->values()
            ->all();

        $employeeFilters = (clone $employeeQuery)
            ->orderBy('name')
            ->limit(150)
            ->get(['id', 'company_id', 'employee_code', 'name', 'department', 'status'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'value' => $employee->id,
                'label' => trim(($employee->employee_code ?: 'EMP-'.$employee->id).' · '.$employee->name),
                'name' => $employee->name,
                'employee_code' => $employee->employee_code,
                'department' => $employee->department,
                'status' => $employee->status,
                'company_id' => $employee->company_id,
            ])
            ->values()
            ->all();

        $attendanceQuery = AttendanceRecord::query()
            ->when(is_array($companyIds), fn (Builder $query): Builder => $query->whereIn('company_id', $companyIds ?: [0]));
        $attendanceTotal = (clone $attendanceQuery)->count();
        $attendancePresent = (clone $attendanceQuery)
            ->whereIn('status', ['present', 'late', 'early_leave', 'half_day', 'overtime'])
            ->count();

        $reportTypes = [
            'Employee Master Register',
            'Headcount & Diversity',
            'Attendance & Absenteeism',
            'Leave Balance & Utilization',
            'Payroll Register',
            'Salary Cost by Cost Center',
            'Statutory Deduction Summary',
            'Recruitment Funnel & Source',
            'Performance Distribution',
            'Probation & Confirmation Due',
            'Attrition & Exit Analysis',
            'Exit Interview Summary',
            'Audit & Export Log',
        ];

        return [
            'source' => 'laravel-sqlite',
            'export_url' => route('hr.employees.export', [], false),
            'can_export' => true,
            'formats' => [
                ['value' => 'excel', 'label' => 'Excel'],
                ['value' => 'pdf', 'label' => 'PDF'],
                ['value' => 'csv', 'label' => 'CSV'],
            ],
            'status_filters' => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
                ['value' => 'on_notice', 'label' => 'On Notice'],
                ['value' => 'separated', 'label' => 'Separated'],
            ],
            'company_filters' => $companyFilters,
            'department_filters' => $departmentFilters,
            'employee_filters' => $employeeFilters,
            'period_filters' => [
                ['value' => now()->format('Y-m'), 'label' => now()->format('F Y')],
                ['value' => now()->subMonth()->format('Y-m'), 'label' => now()->subMonth()->format('F Y')],
                ['value' => now()->month >= 4 ? now()->format('Y').'-'.now()->addYear()->format('y') : now()->subYear()->format('Y').'-'.now()->format('y'), 'label' => 'Current financial year'],
            ],
            'report_types' => $reportTypes,
            'report_catalog' => collect($reportTypes)
                ->map(fn (string $name): array => [
                    'value' => $name,
                    'label' => $name,
                ])
                ->values()
                ->all(),
            'summary' => [
                'employees_in_scope' => (clone $employeeQuery)->count(),
                'average_attendance_percent' => $attendanceTotal > 0 ? round($attendancePresent / $attendanceTotal * 100) : null,
                'departments' => count($departmentFilters),
                'exports_audited' => AuditEvent::query()
                    ->where('user_id', $user->id)
                    ->where('event_type', 'hr.employee_report.exported')
                    ->count(),
            ],
            'default_columns' => [
                'Employee Code',
                'Employee Name',
                'Company',
                'Department',
                'Designation',
                'Status',
                'Joining Date',
                'Manager',
            ],
            'custom_mis_store_url' => route('settings.system-settings.store', [], false),
            'custom_mis_setting_key' => 'hr.custom_mis_reports',
            'can_create_custom_mis' => $user->can('create', SystemSetting::class),
            'compensation_visible' => $user->hasPermission('hr.manage') || $user->hasPermission('payroll.view'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function afterSalesOptions(?User $user): ?array
    {
        if (! $user || ! $user->can('viewAny', ServiceTicket::class)) {
            return null;
        }

        if ($user->hasPermission('buyer.view')) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);

        $ticketsQuery = ServiceTicket::query()
            ->with(['booking', 'project', 'unit', 'customer', 'assignedTo', 'workOrders.assignedTo', 'workOrders.vendor'])
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]));

        $tickets = (clone $ticketsQuery)
            ->orderByRaw("case when status in ('open', 'assigned', 'in_progress') then 0 else 1 end")
            ->orderBy('sla_due_at')
            ->limit(12)
            ->get()
            ->map(fn (ServiceTicket $ticket): array => $this->serviceTicketBootstrapRow($ticket))
            ->values()
            ->all();

        $bookings = Booking::query()
            ->with(['project:id,code,name', 'unit:id,unit_code,unit_number', 'customer:id,code,name'])
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->where('status', 'confirmed')
            ->orderByDesc('booked_on')
            ->limit(25)
            ->get(['id', 'company_id', 'project_id', 'project_unit_id', 'customer_id', 'booking_code', 'status'])
            ->map(fn (Booking $booking): array => [
                'id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'status' => $booking->status,
                'project' => $booking->project ? [
                    'id' => $booking->project->id,
                    'code' => $booking->project->code,
                    'name' => $booking->project->name,
                ] : null,
                'unit' => $booking->unit ? [
                    'id' => $booking->unit->id,
                    'unit_code' => $booking->unit->unit_code,
                    'unit_number' => $booking->unit->unit_number,
                ] : null,
                'customer' => $booking->customer ? [
                    'id' => $booking->customer->id,
                    'code' => $booking->customer->code,
                    'name' => $booking->customer->name,
                ] : null,
            ])
            ->values()
            ->all();

        $assignees = User::query()
            ->with('role:id,name,slug,permissions')
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->where('status', 'active')
            ->whereNotNull('company_id')
            ->orderBy('name')
            ->get(['id', 'company_id', 'role_id', 'name', 'email'])
            ->filter(function (User $assignee): bool {
                $permissions = $assignee->role?->permissions ?? [];

                return in_array('after_sales.manage', $permissions, true)
                    || in_array('construction.manage', $permissions, true);
            })
            ->map(fn (User $assignee): array => [
                'id' => $assignee->id,
                'name' => $assignee->name,
                'email' => $assignee->email,
                'role' => $assignee->role?->name,
            ])
            ->values()
            ->all();

        return [
            'source' => 'laravel-sqlite',
            'index_url' => route('after-sales.tickets.index', [], false),
            'store_url' => route('after-sales.tickets.store', [], false),
            'assign_url_template' => '/after-sales/tickets/__TICKET__/assign',
            'resolve_url_template' => '/after-sales/tickets/__TICKET__/resolve',
            'close_url_template' => '/after-sales/tickets/__TICKET__/close',
            'work_orders_url' => route('after-sales.work-orders.index', [], false),
            'work_order_store_url' => route('after-sales.work-orders.store', [], false),
            'work_order_complete_url_template' => '/after-sales/work-orders/__WORK_ORDER__/complete',
            'can_create' => $user->can('create', ServiceTicket::class),
            'can_assign' => $user->hasPermission('after_sales.manage'),
            'can_resolve' => $user->hasPermission('after_sales.manage'),
            'can_close' => $user->hasPermission('after_sales.approve'),
            'can_manage_work_orders' => $user->can('create', MaintenanceWorkOrder::class),
            'tickets' => $tickets,
            'bookings' => $bookings,
            'assignees' => $assignees,
            'categories' => [
                ['value' => 'defect', 'label' => 'Defect'],
                ['value' => 'maintenance', 'label' => 'Maintenance'],
                ['value' => 'billing', 'label' => 'Billing'],
                ['value' => 'documentation', 'label' => 'Documentation'],
                ['value' => 'society', 'label' => 'Society'],
                ['value' => 'other', 'label' => 'Other'],
            ],
            'priorities' => [
                ['value' => 'low', 'label' => 'Low'],
                ['value' => 'medium', 'label' => 'Medium'],
                ['value' => 'high', 'label' => 'High'],
                ['value' => 'critical', 'label' => 'Critical'],
            ],
            'summary' => [
                'open_tickets' => (clone $ticketsQuery)->whereIn('status', ['open', 'assigned', 'in_progress'])->count(),
                'resolved_mtd' => (clone $ticketsQuery)->whereIn('status', ['resolved', 'closed'])->where('resolved_at', '>=', now()->startOfMonth())->count(),
                'sla_breached' => (clone $ticketsQuery)->whereIn('status', ['open', 'assigned', 'in_progress'])->where('sla_due_at', '<', now())->count(),
                'total_tickets' => (clone $ticketsQuery)->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceTicketBootstrapRow(ServiceTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'source' => $ticket->source,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'status' => $ticket->status,
            'sla_due_at' => $ticket->sla_due_at?->toISOString(),
            'resolved_at' => $ticket->resolved_at?->toISOString(),
            'closed_at' => $ticket->closed_at?->toISOString(),
            'customer_rating' => $ticket->customer_rating,
            'booking' => $ticket->booking ? [
                'id' => $ticket->booking->id,
                'booking_code' => $ticket->booking->booking_code,
            ] : null,
            'project' => $ticket->project ? [
                'id' => $ticket->project->id,
                'code' => $ticket->project->code,
                'name' => $ticket->project->name,
            ] : null,
            'unit' => $ticket->unit ? [
                'id' => $ticket->unit->id,
                'unit_code' => $ticket->unit->unit_code,
                'unit_number' => $ticket->unit->unit_number,
            ] : null,
            'customer' => $ticket->customer ? [
                'id' => $ticket->customer->id,
                'code' => $ticket->customer->code,
                'name' => $ticket->customer->name,
            ] : null,
            'assigned_to' => $ticket->assignedTo ? [
                'id' => $ticket->assignedTo->id,
                'name' => $ticket->assignedTo->name,
                'email' => $ticket->assignedTo->email,
            ] : null,
            'work_orders' => $ticket->workOrders
                ->map(fn (MaintenanceWorkOrder $workOrder): array => [
                    'id' => $workOrder->id,
                    'work_order_number' => $workOrder->work_order_number,
                    'status' => $workOrder->status,
                    'scheduled_on' => $workOrder->scheduled_on?->toDateString(),
                    'scope_of_work' => $workOrder->scope_of_work,
                    'assigned_to' => $workOrder->assignedTo ? [
                        'id' => $workOrder->assignedTo->id,
                        'name' => $workOrder->assignedTo->name,
                    ] : null,
                    'vendor' => $workOrder->vendor ? [
                        'id' => $workOrder->vendor->id,
                        'name' => $workOrder->vendor->name,
                    ] : null,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function maintenanceSocietyOptions(?User $user): ?array
    {
        if (! $user || ! $user->can('viewAny', SocietyFormation::class)) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);
        $projectIds = $this->visibleProjectIds($user);
        $canViewHandover = $user->can('viewAny', CommonAreaHandoverItem::class);
        $canViewDues = $user->can('viewAny', MaintenanceDue::class);

        $societyQuery = SocietyFormation::query()
            ->with(['project', 'createdBy', 'updatedBy'])
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]));

        $handoverQuery = CommonAreaHandoverItem::query()
            ->with(['project', 'societyFormation', 'responsibleUser', 'signedOffBy'])
            ->when(! $canViewHandover, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]));

        $dueQuery = MaintenanceDue::query()
            ->with(['project', 'booking', 'customer', 'unit', 'raisedBy', 'paidBy'])
            ->when(! $canViewDues, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]));

        $projects = Project::query()
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('id', $projectIds ?: [0]))
            ->orderBy('code')
            ->limit(40)
            ->get(['id', 'company_id', 'code', 'name'])
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'company_id' => $project->company_id,
                'code' => $project->code,
                'name' => $project->name,
                'label' => $project->code.' · '.$project->name,
            ])
            ->values()
            ->all();

        $bookings = Booking::query()
            ->with(['project:id,code,name', 'unit:id,unit_code,unit_number', 'customer:id,code,name'])
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]))
            ->whereIn('status', ['confirmed', 'agreement_pending', 'registered'])
            ->orderByDesc('booked_on')
            ->limit(40)
            ->get(['id', 'company_id', 'project_id', 'project_unit_id', 'customer_id', 'booking_code', 'status'])
            ->map(fn (Booking $booking): array => [
                'id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'status' => $booking->status,
                'project' => $booking->project ? [
                    'id' => $booking->project->id,
                    'code' => $booking->project->code,
                    'name' => $booking->project->name,
                ] : null,
                'unit' => $booking->unit ? [
                    'id' => $booking->unit->id,
                    'unit_code' => $booking->unit->unit_code,
                    'unit_number' => $booking->unit->unit_number,
                ] : null,
                'customer' => $booking->customer ? [
                    'id' => $booking->customer->id,
                    'code' => $booking->customer->code,
                    'name' => $booking->customer->name,
                ] : null,
            ])
            ->values()
            ->all();

        $totalChecklist = (clone $handoverQuery)->sum('checklist_total');
        $completedChecklist = (clone $handoverQuery)->sum('checklist_completed');
        $dueAmount = (clone $dueQuery)->whereIn('status', ['due', 'overdue'])->sum('balance_amount');
        $collectedAmount = (clone $dueQuery)->where('status', 'paid')->sum('paid_amount');

        return [
            'source' => 'laravel-sqlite',
            'societies_index_url' => route('maintenance.societies.index', [], false),
            'societies_store_url' => route('maintenance.societies.store', [], false),
            'society_status_url_template' => '/maintenance/societies/__SOCIETY__/status',
            'handover_items_index_url' => route('maintenance.handover-items.index', [], false),
            'handover_item_update_url_template' => '/maintenance/handover-items/__HANDOVER_ITEM__',
            'handover_item_signoff_url_template' => '/maintenance/handover-items/__HANDOVER_ITEM__/sign-off',
            'dues_index_url' => route('maintenance.dues.index', [], false),
            'dues_store_url' => route('maintenance.dues.store', [], false),
            'due_mark_paid_url_template' => '/maintenance/dues/__DUE__/mark-paid',
            'due_remind_url_template' => '/maintenance/dues/__DUE__/remind',
            'can_create_society' => $user->can('create', SocietyFormation::class),
            'can_view_handover' => $canViewHandover,
            'can_update_handover' => $user->can('create', CommonAreaHandoverItem::class),
            'can_signoff_handover' => $user->hasPermission('after_sales.approve') || $user->hasPermission('possession.approve'),
            'can_view_due' => $canViewDues,
            'can_raise_due' => $user->can('create', MaintenanceDue::class),
            'can_mark_due_paid' => $user->hasPermission('finance.manage') || $user->hasPermission('collections.manage'),
            'can_remind_due' => $user->hasPermission('after_sales.manage') || $user->hasPermission('collections.manage'),
            'projects' => $projects,
            'bookings' => $bookings,
            'societies' => (clone $societyQuery)
                ->orderByDesc('updated_at')
                ->limit(20)
                ->get()
                ->map(fn (SocietyFormation $formation): array => $this->societyFormationBootstrapRow($formation))
                ->values()
                ->all(),
            'handover_items' => (clone $handoverQuery)
                ->orderBy('facility_name')
                ->limit(30)
                ->get()
                ->map(fn (CommonAreaHandoverItem $item): array => $this->commonAreaHandoverItemBootstrapRow($item))
                ->values()
                ->all(),
            'maintenance_dues' => (clone $dueQuery)
                ->orderByRaw("case when status in ('due', 'overdue') then 0 else 1 end")
                ->orderBy('due_on')
                ->limit(30)
                ->get()
                ->map(fn (MaintenanceDue $due): array => $this->maintenanceDueBootstrapRow($due))
                ->values()
                ->all(),
            'summary' => [
                'societies_formed' => (clone $societyQuery)->whereIn('status', ['formed', 'handed_over'])->count(),
                'societies_in_progress' => (clone $societyQuery)->whereIn('status', ['draft', 'application_filed', 'in_progress', 'blocked'])->count(),
                'handover_percent' => $totalChecklist > 0 ? round($completedChecklist / $totalChecklist * 100) : 0,
                'pending_common_works' => (clone $handoverQuery)->whereIn('status', ['pending', 'in_progress', 'pending_snags'])->count(),
                'maintenance_collected' => round((float) $collectedAmount, 2),
                'maintenance_due' => round((float) $dueAmount, 2),
            ],
            'statuses' => [
                'society' => ['draft', 'application_filed', 'in_progress', 'formed', 'handed_over', 'blocked'],
                'handover' => ['pending', 'in_progress', 'pending_snags', 'complete'],
                'due' => ['due', 'overdue', 'paid', 'cancelled'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function societyFormationBootstrapRow(SocietyFormation $formation): array
    {
        return [
            'id' => $formation->id,
            'formation_number' => $formation->formation_number,
            'society_name' => $formation->society_name,
            'association_type' => $formation->association_type,
            'total_units' => (int) $formation->total_units,
            'occupied_units' => (int) $formation->occupied_units,
            'registration_number' => $formation->registration_number,
            'status' => $formation->status,
            'progress_percent' => (int) $formation->progress_percent,
            'current_stage' => $formation->current_stage,
            'next_step' => $formation->next_step,
            'project' => $formation->project ? [
                'id' => $formation->project->id,
                'code' => $formation->project->code,
                'name' => $formation->project->name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function commonAreaHandoverItemBootstrapRow(CommonAreaHandoverItem $item): array
    {
        return [
            'id' => $item->id,
            'item_number' => $item->item_number,
            'facility_name' => $item->facility_name,
            'category' => $item->category,
            'checklist_total' => (int) $item->checklist_total,
            'checklist_completed' => (int) $item->checklist_completed,
            'completion_percent' => $item->checklist_total > 0 ? round($item->checklist_completed / $item->checklist_total * 100) : 0,
            'status' => $item->status,
            'target_completion_on' => $item->target_completion_on?->toDateString(),
            'signed_off_on' => $item->signed_off_on?->toDateString(),
            'project' => $item->project ? [
                'id' => $item->project->id,
                'code' => $item->project->code,
                'name' => $item->project->name,
            ] : null,
            'society_formation' => $item->societyFormation ? [
                'id' => $item->societyFormation->id,
                'society_name' => $item->societyFormation->society_name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function maintenanceDueBootstrapRow(MaintenanceDue $due): array
    {
        return [
            'id' => $due->id,
            'due_number' => $due->due_number,
            'period_start_on' => $due->period_start_on?->toDateString(),
            'period_end_on' => $due->period_end_on?->toDateString(),
            'due_on' => $due->due_on?->toDateString(),
            'amount' => (float) $due->amount,
            'paid_amount' => (float) $due->paid_amount,
            'balance_amount' => (float) $due->balance_amount,
            'status' => $due->status,
            'last_reminded_at' => $due->last_reminded_at?->toISOString(),
            'project' => $due->project ? [
                'id' => $due->project->id,
                'code' => $due->project->code,
                'name' => $due->project->name,
            ] : null,
            'booking' => $due->booking ? [
                'id' => $due->booking->id,
                'booking_code' => $due->booking->booking_code,
            ] : null,
            'customer' => $due->customer ? [
                'id' => $due->customer->id,
                'code' => $due->customer->code,
                'name' => $due->customer->name,
            ] : null,
            'unit' => $due->unit ? [
                'id' => $due->unit->id,
                'unit_code' => $due->unit->unit_code,
                'unit_number' => $due->unit->unit_number,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function legalComplianceOptions(?User $user): ?array
    {
        if (! $user || ! $user->can('viewAny', ReraRegistration::class)) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);

        $reraQuery = ReraRegistration::query()
            ->with(['project', 'createdBy', 'verifiedBy'])
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]));

        $approvalQuery = ProjectApproval::query()
            ->with(['project', 'responsibleUser', 'verifiedBy'])
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]));

        $obligationQuery = ComplianceObligation::query()
            ->with(['project', 'assignedTo', 'completedBy'])
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]));

        $projects = Project::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('code')
            ->limit(60)
            ->get(['id', 'company_id', 'code', 'name'])
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'company_id' => $project->company_id,
                'code' => $project->code,
                'name' => $project->name,
                'label' => $project->code.' · '.$project->name,
            ])
            ->values()
            ->all();

        return [
            'source' => 'laravel-sqlite',
            'rera_index_url' => route('legal.rera-registrations.index', [], false),
            'rera_store_url' => route('legal.rera-registrations.store', [], false),
            'rera_verify_url_template' => '/legal/rera-registrations/__RERA__/verify',
            'approval_index_url' => route('legal.project-approvals.index', [], false),
            'approval_store_url' => route('legal.project-approvals.store', [], false),
            'approval_verify_url_template' => '/legal/project-approvals/__APPROVAL__/verify',
            'obligation_index_url' => route('legal.compliance-obligations.index', [], false),
            'obligation_store_url' => route('legal.compliance-obligations.store', [], false),
            'obligation_complete_url_template' => '/legal/compliance-obligations/__OBLIGATION__/complete',
            'can_create' => $user->hasPermission('legal.manage'),
            'can_verify' => $user->hasPermission('legal.approve'),
            'can_complete' => $user->hasPermission('legal.manage') || $user->hasPermission('legal.approve'),
            'projects' => $projects,
            'rera_registrations' => (clone $reraQuery)
                ->orderByRaw('expires_on IS NULL, expires_on ASC')
                ->limit(10)
                ->get()
                ->map(fn (ReraRegistration $registration): array => $this->reraBootstrapRow($registration))
                ->values()
                ->all(),
            'project_approvals' => (clone $approvalQuery)
                ->orderByRaw('expires_on IS NULL, expires_on ASC')
                ->limit(12)
                ->get()
                ->map(fn (ProjectApproval $approval): array => $this->projectApprovalBootstrapRow($approval))
                ->values()
                ->all(),
            'compliance_obligations' => (clone $obligationQuery)
                ->orderBy('due_on')
                ->limit(12)
                ->get()
                ->map(fn (ComplianceObligation $obligation): array => $this->complianceObligationBootstrapRow($obligation))
                ->values()
                ->all(),
            'summary' => [
                'rera_projects' => (clone $reraQuery)->distinct('project_id')->count('project_id'),
                'approvals_valid' => (clone $approvalQuery)->whereIn('status', ['approved', 'verified'])->count(),
                'expiring_soon' => (clone $reraQuery)->whereDate('expires_on', '<=', now()->addDays(30)->toDateString())->count()
                    + (clone $approvalQuery)->whereDate('expires_on', '<=', now()->addDays(30)->toDateString())->count(),
                'compliance_due' => (clone $obligationQuery)->where('status', 'open')->whereDate('due_on', '<=', now()->addDays(30)->toDateString())->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reraBootstrapRow(ReraRegistration $registration): array
    {
        return [
            'id' => $registration->id,
            'registration_number' => $registration->registration_number,
            'authority_name' => $registration->authority_name,
            'state_code' => $registration->state_code,
            'registered_on' => $registration->registered_on?->toDateString(),
            'expires_on' => $registration->expires_on?->toDateString(),
            'status' => $registration->status,
            'document_reference' => $registration->document_reference,
            'verified_at' => $registration->verified_at?->toISOString(),
            'project' => $registration->project ? [
                'id' => $registration->project->id,
                'code' => $registration->project->code,
                'name' => $registration->project->name,
            ] : null,
            'created_by' => $registration->createdBy ? [
                'id' => $registration->createdBy->id,
                'name' => $registration->createdBy->name,
            ] : null,
            'verified_by' => $registration->verifiedBy ? [
                'id' => $registration->verifiedBy->id,
                'name' => $registration->verifiedBy->name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectApprovalBootstrapRow(ProjectApproval $approval): array
    {
        return [
            'id' => $approval->id,
            'approval_code' => $approval->approval_code,
            'approval_type' => $approval->approval_type,
            'authority_name' => $approval->authority_name,
            'application_number' => $approval->application_number,
            'applied_on' => $approval->applied_on?->toDateString(),
            'approved_on' => $approval->approved_on?->toDateString(),
            'expires_on' => $approval->expires_on?->toDateString(),
            'status' => $approval->status,
            'required_for' => $approval->required_for,
            'document_reference' => $approval->document_reference,
            'verified_at' => $approval->verified_at?->toISOString(),
            'project' => $approval->project ? [
                'id' => $approval->project->id,
                'code' => $approval->project->code,
                'name' => $approval->project->name,
            ] : null,
            'responsible_user' => $approval->responsibleUser ? [
                'id' => $approval->responsibleUser->id,
                'name' => $approval->responsibleUser->name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function complianceObligationBootstrapRow(ComplianceObligation $obligation): array
    {
        return [
            'id' => $obligation->id,
            'obligation_number' => $obligation->obligation_number,
            'title' => $obligation->title,
            'compliance_type' => $obligation->compliance_type,
            'due_on' => $obligation->due_on?->toDateString(),
            'frequency' => $obligation->frequency,
            'priority' => $obligation->priority,
            'status' => $obligation->status,
            'evidence_document_reference' => $obligation->evidence_document_reference,
            'completed_at' => $obligation->completed_at?->toISOString(),
            'project' => $obligation->project ? [
                'id' => $obligation->project->id,
                'code' => $obligation->project->code,
                'name' => $obligation->project->name,
            ] : null,
            'assigned_to' => $obligation->assignedTo ? [
                'id' => $obligation->assignedTo->id,
                'name' => $obligation->assignedTo->name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function documentManagementOptions(?User $user): ?array
    {
        if (! $user || ! $user->can('viewAny', ManagedDocument::class)) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);
        $projectIds = $this->visibleProjectIds($user);

        $documentsQuery = ManagedDocument::query()
            ->with(['company', 'category', 'uploadedBy', 'approvedBy'])
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]));

        $categories = DocumentCategory::query()
            ->with('company:id,code,name')
            ->when(is_array($companyIds), function (Builder $query) use ($companyIds): void {
                $query->where(function (Builder $categoryQuery) use ($companyIds): void {
                    $categoryQuery
                        ->whereNull('company_id')
                        ->orWhereIn('company_id', $companyIds ?: [0]);
                });
            })
            ->where('is_active', true)
            ->orderBy('owner_type')
            ->orderBy('name')
            ->get()
            ->map(fn (DocumentCategory $category): array => $this->documentCategoryBootstrapRow($category))
            ->values()
            ->all();

        $projects = Project::query()
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('id', $projectIds ?: [0]))
            ->orderBy('code')
            ->limit(30)
            ->get(['id', 'company_id', 'code', 'name'])
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'label' => $project->code.' · '.$project->name,
                'owner_type' => 'project',
                'company_id' => $project->company_id,
            ])
            ->values()
            ->all();

        $bookings = Booking::query()
            ->with(['project:id,code,name', 'unit:id,unit_code,unit_number', 'customer:id,name'])
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->orderByDesc('booked_on')
            ->limit(30)
            ->get(['id', 'company_id', 'project_id', 'project_unit_id', 'customer_id', 'booking_code'])
            ->map(fn (Booking $booking): array => [
                'id' => $booking->id,
                'label' => $booking->booking_code.' · '.($booking->unit?->unit_code ?? $booking->unit?->unit_number ?? 'Unit').' · '.($booking->customer?->name ?? 'Customer'),
                'owner_type' => 'booking',
                'company_id' => $booking->company_id,
            ])
            ->values()
            ->all();

        $employees = Employee::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'company_id', 'employee_code', 'name', 'department'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'label' => $employee->employee_code.' · '.$employee->name.' · '.$employee->department,
                'owner_type' => 'employee',
                'company_id' => $employee->company_id,
            ])
            ->values()
            ->all();

        $customerIds = Booking::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->whereNotNull('customer_id')
            ->distinct()
            ->limit(30)
            ->pluck('customer_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $customers = Customer::query()
            ->whereIn('id', $customerIds ?: [0])
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'label' => $customer->code.' · '.$customer->name,
                'owner_type' => 'customer',
            ])
            ->values()
            ->all();

        return [
            'source' => 'laravel-sqlite',
            'index_url' => route('documents.index', [], false),
            'categories_url' => route('documents.categories.index', [], false),
            'store_url' => route('documents.store', [], false),
            'approve_url_template' => '/documents/__DOCUMENT__/approve',
            'download_url_template' => '/documents/__DOCUMENT__/download',
            'can_create' => $user->can('create', ManagedDocument::class),
            'can_approve' => $user->hasPermission('documents.approve'),
            'documents' => (clone $documentsQuery)
                ->where('is_current', true)
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (ManagedDocument $document): array => $this->managedDocumentBootstrapRow($document, $user))
                ->values()
                ->all(),
            'categories' => $categories,
            'owners' => [
                'project' => $projects,
                'booking' => $bookings,
                'customer' => $customers,
                'employee' => $employees,
            ],
            'file_policy' => [
                'storage_disk' => 'local',
                'storage_path_prefix' => (string) config('builder360.documents.storage_path_prefix', 'documents/'),
                'allowed_extensions' => (array) config('builder360.documents.allowed_extensions', []),
                'allowed_mime_types' => (array) config('builder360.documents.allowed_mime_types', []),
                'max_file_size_kb' => (int) config('builder360.documents.max_file_size_kb', 10240),
            ],
            'summary' => [
                'total_documents' => (clone $documentsQuery)->count(),
                'current_documents' => (clone $documentsQuery)->where('is_current', true)->count(),
                'expiring_soon' => (clone $documentsQuery)
                    ->where('is_current', true)
                    ->whereNotNull('expires_on')
                    ->whereDate('expires_on', '>=', now()->toDateString())
                    ->whereDate('expires_on', '<=', now()->addDays(30)->toDateString())
                    ->count(),
                'submitted' => (clone $documentsQuery)->where('status', 'submitted')->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentCategoryBootstrapRow(DocumentCategory $category): array
    {
        return [
            'id' => $category->id,
            'code' => $category->code,
            'name' => $category->name,
            'owner_type' => $category->owner_type,
            'expiry_required' => $category->expiry_required,
            'reminder_days_before_expiry' => $category->reminder_days_before_expiry,
            'retention_years' => $category->retention_years,
            'company' => $category->company ? [
                'code' => $category->company->code,
                'name' => $category->company->name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function managedDocumentBootstrapRow(ManagedDocument $document, User $user): array
    {
        return [
            'id' => $document->id,
            'document_number' => $document->document_number,
            'title' => $document->title,
            'owner_type' => $document->owner_type,
            'owner_id' => $document->owner_id,
            'status' => $document->status,
            'original_filename' => $document->original_filename,
            'mime_type' => $document->mime_type,
            'file_size_bytes' => $document->file_size_bytes,
            'download_url' => $user->can('view', $document) ? route('documents.download', $document, false) : null,
            'issue_date' => $document->issue_date?->toDateString(),
            'expires_on' => $document->expires_on?->toDateString(),
            'is_expired' => $document->isExpired(),
            'is_expiring_within_30_days' => $document->isExpiringWithin(30),
            'version' => $document->version,
            'is_current' => $document->is_current,
            'approved_at' => $document->approved_at?->toISOString(),
            'company' => $document->company ? [
                'code' => $document->company->code,
                'name' => $document->company->name,
            ] : null,
            'category' => $document->category ? [
                'id' => $document->category->id,
                'code' => $document->category->code,
                'name' => $document->category->name,
                'owner_type' => $document->category->owner_type,
                'expiry_required' => $document->category->expiry_required,
            ] : null,
            'uploaded_by' => $document->uploadedBy ? [
                'name' => $document->uploadedBy->name,
                'email' => $document->uploadedBy->email,
            ] : null,
            'approved_by' => $document->approvedBy ? [
                'name' => $document->approvedBy->name,
                'email' => $document->approvedBy->email,
            ] : null,
            'created_at' => $document->created_at?->toISOString(),
            'updated_at' => $document->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function constructionSiteOptions(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $canViewConstruction = $user->can('viewAny', DailyProgressReport::class)
            || $user->can('viewAny', ConstructionMilestone::class);
        $canViewProcurement = $user->can('viewAny', PurchaseOrder::class)
            || $user->can('viewAny', PurchaseRequisition::class)
            || $user->can('viewAny', Vendor::class);

        if (! $canViewConstruction && ! $canViewProcurement) {
            return null;
        }

        if ($this->isPartnerPortalUser($user) || $this->isBuyerPortalUser($user)) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);
        $projectIds = $this->visibleProjectIds($user);

        $projectQuery = Project::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('id', $projectIds ?: [0]));

        $milestoneQuery = ConstructionMilestone::query()
            ->with(['project', 'createdBy'])
            ->when(! $canViewConstruction, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]));

        $dailyReportQuery = DailyProgressReport::query()
            ->with(['project', 'preparedBy', 'approvedBy'])
            ->when(! $canViewConstruction, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]));

        $stockItemQuery = StockItem::query()
            ->with(['project', 'movements' => fn ($query) => $query->latest('movement_date')->latest()->limit(3)])
            ->when(! $canViewProcurement, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]));

        $requisitionQuery = PurchaseRequisition::query()
            ->with(['project', 'requestedBy', 'approvedBy'])
            ->when(! $canViewProcurement, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]));

        $purchaseOrderQuery = PurchaseOrder::query()
            ->with(['project', 'vendor', 'purchaseRequisition', 'createdBy', 'approvedBy', 'goodsReceipts'])
            ->when(! $canViewProcurement, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]));

        $vendorQuery = Vendor::query()
            ->when(! $canViewProcurement, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]));

        $vendorPurchaseStats = (clone $purchaseOrderQuery)
            ->get(['id', 'vendor_id', 'po_number', 'po_date', 'status', 'total_amount'])
            ->filter(fn (PurchaseOrder $purchaseOrder): bool => $purchaseOrder->vendor_id !== null)
            ->groupBy('vendor_id')
            ->map(fn ($orders): array => [
                'purchase_orders_count' => $orders->count(),
                'purchase_value_total' => round((float) $orders->sum('total_amount'), 2),
                'open_purchase_value' => round((float) $orders
                    ->whereIn('status', ['draft', 'approved', 'partially_received'])
                    ->sum('total_amount'), 2),
                'latest_purchase_order' => ($latest = $orders->sortByDesc('po_date')->first()) ? [
                    'id' => $latest->id,
                    'po_number' => $latest->po_number,
                    'po_date' => $latest->po_date?->toDateString(),
                    'status' => $latest->status,
                    'total_amount' => (float) $latest->total_amount,
                ] : null,
            ]);

        $stockValue = round((float) (clone $stockItemQuery)->sum('stock_value'), 2);
        $monthlyPoValue = round((float) (clone $purchaseOrderQuery)
            ->whereDate('po_date', '>=', now()->startOfMonth()->toDateString())
            ->sum('total_amount'), 2);
        $openIssues = (clone $dailyReportQuery)
            ->where(function (Builder $query): void {
                $query->whereNotNull('blockers')
                    ->where('blockers', '<>', '');
            })
            ->whereIn('status', ['submitted', 'approved'])
            ->count();

        return [
            'source' => 'laravel-sqlite',
            'milestones_index_url' => route('construction.milestones.index', [], false),
            'milestones_store_url' => route('construction.milestones.store', [], false),
            'daily_reports_index_url' => route('construction.daily-progress-reports.index', [], false),
            'daily_reports_store_url' => route('construction.daily-progress-reports.store', [], false),
            'daily_report_approve_url_template' => '/construction/daily-progress-reports/__REPORT__/approve',
            'daily_report_reject_url_template' => '/construction/daily-progress-reports/__REPORT__/reject',
            'procurement_dashboard_url' => route('procurement.dashboard', [], false),
            'stock_items_index_url' => route('procurement.stock-items.index', [], false),
            'stock_issue_store_url' => route('procurement.stock-issues.store', [], false),
            'stock_return_store_url' => route('procurement.stock-returns.store', [], false),
            'stock_transfer_store_url' => route('procurement.stock-transfers.store', [], false),
            'requisitions_index_url' => route('procurement.requisitions.index', [], false),
            'requisitions_store_url' => route('procurement.requisitions.store', [], false),
            'requisition_approve_url_template' => '/procurement/requisitions/__REQUISITION__/approve',
            'requisition_quote_comparison_url_template' => '/procurement/requisitions/__REQUISITION__/quote-comparison',
            'purchase_orders_index_url' => route('procurement.purchase-orders.index', [], false),
            'purchase_orders_store_url' => route('procurement.purchase-orders.store', [], false),
            'purchase_order_approve_url_template' => '/procurement/purchase-orders/__PURCHASE_ORDER__/approve',
            'goods_receipts_index_url' => route('procurement.goods-receipts.index', [], false),
            'goods_receipts_store_url' => route('procurement.goods-receipts.store', [], false),
            'vendors_index_url' => route('procurement.vendors.index', [], false),
            'vendors_store_url' => route('procurement.vendors.store', [], false),
            'vendor_performance_url_template' => '/procurement/vendors/__VENDOR__/performance',
            'can_view_construction' => $canViewConstruction,
            'can_create_milestone' => $user->can('create', ConstructionMilestone::class),
            'can_create_daily_report' => $user->can('create', DailyProgressReport::class),
            'can_approve_daily_report' => $user->hasPermission('construction.approve'),
            'can_view_procurement' => $canViewProcurement,
            'can_create_requisition' => $user->can('create', PurchaseRequisition::class),
            'can_approve_requisition' => $user->hasPermission('procurement.approve'),
            'can_create_purchase_order' => $user->can('create', PurchaseOrder::class),
            'can_approve_purchase_order' => $user->hasPermission('procurement.approve'),
            'can_receive_goods' => $user->hasPermission('procurement.manage'),
            'can_manage_stock' => $user->hasPermission('procurement.manage'),
            'can_transfer_stock' => $user->hasPermission('procurement.manage'),
            'can_create_vendor' => $user->can('create', Vendor::class),
            'companies' => $this->companyRows($user),
            'vendor_type_options' => [
                ['value' => 'material', 'label' => 'Material Supplier'],
                ['value' => 'contractor', 'label' => 'Contractor'],
                ['value' => 'service', 'label' => 'Service Provider'],
                ['value' => 'consultant', 'label' => 'Consultant'],
            ],
            'vendor_status_options' => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
                ['value' => 'blocked', 'label' => 'Blocked'],
            ],
            'projects' => (clone $projectQuery)
                ->orderBy('code')
                ->limit(60)
                ->get(['id', 'company_id', 'code', 'name', 'city', 'status'])
                ->map(fn (Project $project): array => [
                    'id' => $project->id,
                    'company_id' => $project->company_id,
                    'code' => $project->code,
                    'name' => $project->name,
                    'city' => $project->city,
                    'status' => $project->status,
                    'label' => $project->code.' · '.$project->name,
                ])
                ->values()
                ->all(),
            'milestones' => (clone $milestoneQuery)
                ->orderByRaw("case when status in ('delayed', 'blocked') then 0 when status = 'in_progress' then 1 else 2 end")
                ->orderBy('planned_end_on')
                ->limit(40)
                ->get()
                ->map(fn (ConstructionMilestone $milestone): array => $this->constructionMilestoneBootstrapRow($milestone))
                ->values()
                ->all(),
            'daily_reports' => (clone $dailyReportQuery)
                ->orderByDesc('report_date')
                ->orderByDesc('id')
                ->limit(30)
                ->get()
                ->map(fn (DailyProgressReport $report): array => $this->dailyProgressReportBootstrapRow($report, $user))
                ->values()
                ->all(),
            'stock_items' => (clone $stockItemQuery)
                ->orderByRaw("case when minimum_stock_quantity > 0 and on_hand_quantity <= minimum_stock_quantity then 0 else 1 end")
                ->orderBy('item_code')
                ->limit(40)
                ->get()
                ->map(fn (StockItem $item): array => $this->stockItemBootstrapRow($item))
                ->values()
                ->all(),
            'requisitions' => (clone $requisitionQuery)
                ->orderByRaw("case when status = 'submitted' then 0 when status = 'draft' then 1 else 2 end")
                ->orderByDesc('created_at')
                ->limit(25)
                ->get()
                ->map(fn (PurchaseRequisition $requisition): array => $this->purchaseRequisitionBootstrapRow($requisition, $user))
                ->values()
                ->all(),
            'purchase_orders' => (clone $purchaseOrderQuery)
                ->orderByRaw("case when status = 'draft' then 0 when status in ('approved', 'partially_received') then 1 else 2 end")
                ->orderByDesc('po_date')
                ->orderByDesc('id')
                ->limit(25)
                ->get()
                ->map(fn (PurchaseOrder $purchaseOrder): array => $this->purchaseOrderBootstrapRow($purchaseOrder, $user))
                ->values()
                ->all(),
            'vendors' => (clone $vendorQuery)
                ->orderBy('name')
                ->limit(40)
                ->get(['id', 'company_id', 'vendor_code', 'name', 'vendor_type', 'status', 'metadata'])
                ->map(function (Vendor $vendor) use ($vendorPurchaseStats): array {
                    $stats = $vendorPurchaseStats->get($vendor->id, [
                        'purchase_orders_count' => 0,
                        'purchase_value_total' => 0.0,
                        'open_purchase_value' => 0.0,
                        'latest_purchase_order' => null,
                    ]);

                    return [
                        'id' => $vendor->id,
                        'company_id' => $vendor->company_id,
                        'vendor_code' => $vendor->vendor_code,
                        'name' => $vendor->name,
                        'vendor_type' => $vendor->vendor_type,
                        'category' => $vendor->metadata['category'] ?? $vendor->vendor_type,
                        'status' => $vendor->status,
                        'rating' => (float) ($vendor->metadata['rating'] ?? 0),
                        'purchase_orders_count' => $stats['purchase_orders_count'],
                        'purchase_value_total' => $stats['purchase_value_total'],
                        'open_purchase_value' => $stats['open_purchase_value'],
                        'latest_purchase_order' => $stats['latest_purchase_order'],
                    ];
                })
                ->values()
                ->all(),
            'summary' => [
                'active_milestones' => (clone $milestoneQuery)->whereIn('status', ['planned', 'in_progress', 'delayed', 'blocked'])->count(),
                'delayed_milestones' => (clone $milestoneQuery)->whereIn('status', ['delayed', 'blocked'])->count(),
                'average_progress' => round((float) (clone $milestoneQuery)->avg('progress_percent')),
                'reports_today' => (clone $dailyReportQuery)->whereDate('report_date', now()->toDateString())->count(),
                'open_site_issues' => $openIssues,
                'total_manpower_latest_reports' => (clone $dailyReportQuery)->whereDate('report_date', '>=', now()->subDays(7)->toDateString())->sum('manpower_count'),
                'stock_value' => $stockValue,
                'low_stock_items' => (clone $stockItemQuery)->whereColumn('on_hand_quantity', '<=', 'minimum_stock_quantity')->where('minimum_stock_quantity', '>', 0)->count(),
                'stock_items' => (clone $stockItemQuery)->count(),
                'open_indents' => (clone $requisitionQuery)->whereIn('status', ['draft', 'submitted'])->count(),
                'purchase_orders_month' => (clone $purchaseOrderQuery)->whereDate('po_date', '>=', now()->startOfMonth()->toDateString())->count(),
                'pending_grn' => (clone $purchaseOrderQuery)->whereIn('status', ['approved', 'partially_received'])->count(),
                'po_value_mtd' => $monthlyPoValue,
                'active_vendors' => (clone $vendorQuery)->where('status', 'active')->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function constructionMilestoneBootstrapRow(ConstructionMilestone $milestone): array
    {
        return [
            'id' => $milestone->id,
            'milestone_code' => $milestone->milestone_code,
            'name' => $milestone->name,
            'phase' => $milestone->phase,
            'planned_start' => $milestone->planned_start_on?->toDateString(),
            'planned_finish' => $milestone->planned_end_on?->toDateString(),
            'actual_start' => $milestone->actual_start_on?->toDateString(),
            'actual_finish' => $milestone->actual_end_on?->toDateString(),
            'progress_percent' => (float) $milestone->progress_percent,
            'status' => $milestone->status,
            'owner_name' => $milestone->createdBy?->name ?? 'Construction Team',
            'project' => $milestone->project ? [
                'id' => $milestone->project->id,
                'code' => $milestone->project->code,
                'name' => $milestone->project->name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dailyProgressReportBootstrapRow(DailyProgressReport $report, User $user): array
    {
        $progressItems = collect($report->progress_items ?? []);
        $completion = $progressItems->avg(fn ($item): float => (float) ($item['completion_percent'] ?? $item['progress_percent'] ?? 0));
        $blocked = trim((string) $report->blockers) !== '';

        return [
            'id' => $report->id,
            'report_number' => $report->report_number,
            'report_date' => $report->report_date?->toDateString(),
            'weather' => $report->weather,
            'manpower_count' => (int) $report->manpower_count,
            'progress_items' => $report->progress_items ?? [],
            'materials_used' => $report->materials_used ?? [],
            'equipment_used' => $report->equipment_used ?? [],
            'work_summary' => $report->work_summary,
            'safety_observations' => $report->safety_observations,
            'quality_observations' => $report->quality_observations,
            'blockers' => $report->blockers,
            'computed_completion_percent' => round((float) $completion),
            'open_issues' => $blocked ? 1 : 0,
            'status' => $report->status,
            'can_approve' => $user->can('approve', $report),
            'project' => $report->project ? [
                'id' => $report->project->id,
                'code' => $report->project->code,
                'name' => $report->project->name,
            ] : null,
            'prepared_by' => $report->preparedBy ? [
                'id' => $report->preparedBy->id,
                'name' => $report->preparedBy->name,
                'email' => $report->preparedBy->email,
            ] : null,
            'approved_by' => $report->approvedBy ? [
                'id' => $report->approvedBy->id,
                'name' => $report->approvedBy->name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stockItemBootstrapRow(StockItem $item): array
    {
        return [
            'id' => $item->id,
            'company_id' => $item->company_id,
            'project_id' => $item->project_id,
            'store_type' => $item->store_type,
            'item_code' => $item->item_code,
            'description' => $item->description,
            'unit' => $item->unit,
            'on_hand_quantity' => (float) $item->on_hand_quantity,
            'stock_value' => (float) $item->stock_value,
            'average_rate' => (float) $item->average_rate,
            'minimum_stock_quantity' => (float) $item->minimum_stock_quantity,
            'is_below_minimum' => $item->isBelowMinimum(),
            'status' => $item->status,
            'last_movement_at' => $item->last_movement_at?->toISOString(),
            'project' => $item->project ? [
                'id' => $item->project->id,
                'code' => $item->project->code,
                'name' => $item->project->name,
            ] : null,
            'recent_movements' => $item->movements
                ->map(fn ($movement): array => [
                    'id' => $movement->id,
                    'movement_type' => $movement->movement_type,
                    'movement_date' => $movement->movement_date?->toDateString(),
                    'quantity' => (float) $movement->quantity,
                    'amount' => (float) $movement->amount,
                    'source_type' => $movement->source_type,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseRequisitionBootstrapRow(PurchaseRequisition $requisition, User $user): array
    {
        return [
            'id' => $requisition->id,
            'requisition_number' => $requisition->requisition_number,
            'department' => $requisition->department,
            'required_by' => $requisition->required_by?->toDateString(),
            'priority' => $requisition->priority,
            'status' => $requisition->status,
            'items' => $requisition->items ?? [],
            'estimated_total' => (float) $requisition->estimated_total,
            'purpose' => $requisition->purpose,
            'can_approve' => $user->can('approve', $requisition),
            'project' => $requisition->project ? [
                'id' => $requisition->project->id,
                'code' => $requisition->project->code,
                'name' => $requisition->project->name,
            ] : null,
            'requested_by' => $requisition->requestedBy ? [
                'id' => $requisition->requestedBy->id,
                'name' => $requisition->requestedBy->name,
            ] : null,
            'approved_by' => $requisition->approvedBy ? [
                'id' => $requisition->approvedBy->id,
                'name' => $requisition->approvedBy->name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseOrderBootstrapRow(PurchaseOrder $purchaseOrder, User $user): array
    {
        return [
            'id' => $purchaseOrder->id,
            'po_number' => $purchaseOrder->po_number,
            'po_date' => $purchaseOrder->po_date?->toDateString(),
            'expected_delivery_on' => $purchaseOrder->expected_delivery_on?->toDateString(),
            'status' => $purchaseOrder->status,
            'payment_terms' => $purchaseOrder->payment_terms,
            'items' => $purchaseOrder->items ?? [],
            'subtotal' => (float) $purchaseOrder->subtotal,
            'tax_amount' => (float) $purchaseOrder->tax_amount,
            'total_amount' => (float) $purchaseOrder->total_amount,
            'can_approve' => $user->can('approve', $purchaseOrder),
            'can_receive' => $user->can('receive', $purchaseOrder),
            'project' => $purchaseOrder->project ? [
                'id' => $purchaseOrder->project->id,
                'code' => $purchaseOrder->project->code,
                'name' => $purchaseOrder->project->name,
            ] : null,
            'vendor' => $purchaseOrder->vendor ? [
                'id' => $purchaseOrder->vendor->id,
                'vendor_code' => $purchaseOrder->vendor->vendor_code,
                'name' => $purchaseOrder->vendor->name,
            ] : null,
            'purchase_requisition' => $purchaseOrder->purchaseRequisition ? [
                'id' => $purchaseOrder->purchaseRequisition->id,
                'requisition_number' => $purchaseOrder->purchaseRequisition->requisition_number,
                'status' => $purchaseOrder->purchaseRequisition->status,
            ] : null,
            'goods_receipts_count' => $purchaseOrder->goodsReceipts->count(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function constructionBoqOptions(?User $user): ?array
    {
        if (! $user || ! $user->can('viewAny', BoqItem::class)) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);
        $projectIds = $this->visibleProjectIds($user);

        $boqQuery = BoqItem::query()
            ->with(['project', 'milestone', 'vendor', 'createdBy'])
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]));

        $measurementQuery = ContractorMeasurement::query()
            ->with(['project', 'vendor', 'submittedBy', 'approvedBy'])
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]));

        $billQuery = ContractorBill::query()
            ->with(['project', 'vendor', 'measurement', 'preparedBy', 'approvedBy', 'paidBy'])
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]));

        $projects = Project::query()
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('id', $projectIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('code')
            ->limit(40)
            ->get(['id', 'company_id', 'code', 'name'])
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'company_id' => $project->company_id,
                'code' => $project->code,
                'name' => $project->name,
                'label' => $project->code.' · '.$project->name,
            ])
            ->values()
            ->all();

        $contractors = Vendor::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->where('vendor_type', 'contractor')
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(40)
            ->get(['id', 'company_id', 'vendor_code', 'name'])
            ->map(fn (Vendor $vendor): array => [
                'id' => $vendor->id,
                'company_id' => $vendor->company_id,
                'vendor_code' => $vendor->vendor_code,
                'name' => $vendor->name,
                'label' => $vendor->vendor_code.' · '.$vendor->name,
            ])
            ->values()
            ->all();

        $milestones = ConstructionMilestone::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]))
            ->orderBy('planned_start_on')
            ->limit(60)
            ->get(['id', 'company_id', 'project_id', 'milestone_code', 'name'])
            ->map(fn (ConstructionMilestone $milestone): array => [
                'id' => $milestone->id,
                'company_id' => $milestone->company_id,
                'project_id' => $milestone->project_id,
                'milestone_code' => $milestone->milestone_code,
                'name' => $milestone->name,
                'label' => $milestone->milestone_code.' · '.$milestone->name,
            ])
            ->values()
            ->all();

        $billedMeasurementIds = (clone $billQuery)
            ->whereNotNull('contractor_measurement_id')
            ->pluck('contractor_measurement_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $billableMeasurements = (clone $measurementQuery)
            ->where('status', 'approved')
            ->whereNotIn('id', $billedMeasurementIds ?: [0])
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (ContractorMeasurement $measurement): array => $this->contractorMeasurementBootstrapRow($measurement))
            ->values()
            ->all();

        return [
            'source' => 'laravel-sqlite',
            'boq_index_url' => route('construction.boq-items.index', [], false),
            'boq_store_url' => route('construction.boq-items.store', [], false),
            'measurement_index_url' => route('construction.contractor-measurements.index', [], false),
            'measurement_store_url' => route('construction.contractor-measurements.store', [], false),
            'measurement_approve_url_template' => '/construction/contractor-measurements/__MEASUREMENT__/approve',
            'measurement_reject_url_template' => '/construction/contractor-measurements/__MEASUREMENT__/reject',
            'bill_index_url' => route('construction.contractor-bills.index', [], false),
            'bill_store_url' => route('construction.contractor-bills.store', [], false),
            'bill_approve_url_template' => '/construction/contractor-bills/__BILL__/approve',
            'bill_mark_paid_url_template' => '/construction/contractor-bills/__BILL__/mark-paid',
            'can_create_boq' => $user->can('create', BoqItem::class),
            'can_create_measurement' => $user->can('create', ContractorMeasurement::class),
            'can_approve_measurement' => $user->hasPermission('construction.approve'),
            'can_create_bill' => $user->can('create', ContractorBill::class),
            'can_approve_bill' => $user->hasPermission('construction.approve'),
            'can_mark_bill_paid' => $user->hasPermission('finance.manage') || $user->hasPermission('finance.approve'),
            'projects' => $projects,
            'contractors' => $contractors,
            'milestones' => $milestones,
            'boq_items' => (clone $boqQuery)
                ->orderBy('boq_code')
                ->limit(40)
                ->get()
                ->map(fn (BoqItem $item): array => $this->boqItemBootstrapRow($item))
                ->values()
                ->all(),
            'measurements' => (clone $measurementQuery)
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (ContractorMeasurement $measurement): array => $this->contractorMeasurementBootstrapRow($measurement))
                ->values()
                ->all(),
            'billable_measurements' => $billableMeasurements,
            'bills' => (clone $billQuery)
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (ContractorBill $bill): array => $this->contractorBillBootstrapRow($bill))
                ->values()
                ->all(),
            'summary' => [
                'boq_items' => (clone $boqQuery)->count(),
                'budget_amount' => round((float) (clone $boqQuery)->sum('budget_amount'), 2),
                'certified_amount' => round((float) (clone $boqQuery)->sum('certified_amount'), 2),
                'pending_measurements' => (clone $measurementQuery)->where('status', 'submitted')->count(),
                'approved_measurements' => (clone $measurementQuery)->where('status', 'approved')->count(),
                'pending_bills' => (clone $billQuery)->where('status', 'submitted')->count(),
                'payable_balance' => round((float) (clone $billQuery)->whereIn('status', ['approved', 'partially_paid'])->sum('balance_amount'), 2),
            ],
            'statuses' => [
                'boq' => ['active', 'inactive', 'closed'],
                'measurement' => ['submitted', 'approved', 'rejected'],
                'bill' => ['submitted', 'approved', 'partially_paid', 'paid', 'rejected'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function boqItemBootstrapRow(BoqItem $item): array
    {
        return [
            'id' => $item->id,
            'boq_code' => $item->boq_code,
            'trade' => $item->trade,
            'description' => $item->description,
            'unit' => $item->unit,
            'planned_quantity' => (float) $item->planned_quantity,
            'rate' => (float) $item->rate,
            'budget_amount' => (float) $item->budget_amount,
            'measured_quantity' => (float) $item->measured_quantity,
            'certified_quantity' => (float) $item->certified_quantity,
            'certified_amount' => (float) $item->certified_amount,
            'balance_quantity' => round((float) $item->planned_quantity - (float) $item->certified_quantity, 3),
            'status' => $item->status,
            'project' => $item->project ? [
                'id' => $item->project->id,
                'code' => $item->project->code,
                'name' => $item->project->name,
            ] : null,
            'milestone' => $item->milestone ? [
                'id' => $item->milestone->id,
                'milestone_code' => $item->milestone->milestone_code,
                'name' => $item->milestone->name,
            ] : null,
            'vendor' => $item->vendor ? [
                'id' => $item->vendor->id,
                'vendor_code' => $item->vendor->vendor_code,
                'name' => $item->vendor->name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contractorMeasurementBootstrapRow(ContractorMeasurement $measurement): array
    {
        return [
            'id' => $measurement->id,
            'measurement_number' => $measurement->measurement_number,
            'measurement_date' => $measurement->measurement_date?->toDateString(),
            'bill_reference' => $measurement->bill_reference,
            'status' => $measurement->status,
            'measured_total' => (float) $measurement->measured_total,
            'certified_total' => (float) $measurement->certified_total,
            'lines' => $measurement->lines ?? [],
            'remarks' => $measurement->remarks,
            'project' => $measurement->project ? [
                'id' => $measurement->project->id,
                'code' => $measurement->project->code,
                'name' => $measurement->project->name,
            ] : null,
            'vendor' => $measurement->vendor ? [
                'id' => $measurement->vendor->id,
                'vendor_code' => $measurement->vendor->vendor_code,
                'name' => $measurement->vendor->name,
            ] : null,
            'submitted_by' => $measurement->submittedBy ? [
                'id' => $measurement->submittedBy->id,
                'name' => $measurement->submittedBy->name,
            ] : null,
            'approved_by' => $measurement->approvedBy ? [
                'id' => $measurement->approvedBy->id,
                'name' => $measurement->approvedBy->name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contractorBillBootstrapRow(ContractorBill $bill): array
    {
        return [
            'id' => $bill->id,
            'bill_number' => $bill->bill_number,
            'bill_date' => $bill->bill_date?->toDateString(),
            'status' => $bill->status,
            'gross_amount' => (float) $bill->gross_amount,
            'retention_percent' => (float) $bill->retention_percent,
            'retention_amount' => (float) $bill->retention_amount,
            'deduction_amount' => (float) $bill->deduction_amount,
            'tax_amount' => (float) $bill->tax_amount,
            'payable_amount' => (float) $bill->payable_amount,
            'paid_amount' => (float) $bill->paid_amount,
            'balance_amount' => (float) $bill->balance_amount,
            'project' => $bill->project ? [
                'id' => $bill->project->id,
                'code' => $bill->project->code,
                'name' => $bill->project->name,
            ] : null,
            'vendor' => $bill->vendor ? [
                'id' => $bill->vendor->id,
                'vendor_code' => $bill->vendor->vendor_code,
                'name' => $bill->vendor->name,
            ] : null,
            'measurement' => $bill->measurement ? [
                'id' => $bill->measurement->id,
                'measurement_number' => $bill->measurement->measurement_number,
                'certified_total' => (float) $bill->measurement->certified_total,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function adminGovernanceOptions(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $canViewUsers = $user->can('viewAny', User::class);
        $canViewRoles = $user->can('viewAny', Role::class);
        $canViewSettings = $user->can('viewAny', SystemSetting::class);
        $canViewDataImports = $user->can('viewAny', DataImportBatch::class);
        $canManageCompanies = $user->can('create', Company::class);

        if (! $canViewUsers && ! $canViewRoles && ! $canViewSettings && ! $canViewDataImports) {
            return null;
        }

        if ($this->isPartnerPortalUser($user) || $this->isBuyerPortalUser($user)) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);

        $userQuery = User::query()
            ->with(['role:id,slug,name,scope_level', 'company:id,code,name'])
            ->when(! $canViewUsers, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]));

        $roleQuery = Role::query()
            ->withCount('users')
            ->when(! $canViewRoles, fn (Builder $query) => $query->whereRaw('1 = 0'));

        $moduleQuery = ErpModule::query()
            ->where('is_active', true)
            ->orderBy('group_name')
            ->orderBy('sort_order');

        $settingQuery = SystemSetting::query()
            ->with(['company:id,code,name', 'createdBy:id,name,email', 'approvedBy:id,name,email'])
            ->when(! $canViewSettings, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when(is_array($companyIds), function (Builder $query) use ($companyIds): void {
                $query->where(function (Builder $scoped) use ($companyIds): void {
                    $scoped->whereIn('company_id', $companyIds ?: [0])
                        ->orWhereNull('company_id');
                });
            });

        $dataImportQuery = DataImportBatch::query()
            ->with(['company:id,code,name', 'createdBy:id,name,email', 'postedBy:id,name,email'])
            ->when(! $canViewDataImports, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]));

        $workflowSetting = (clone $settingQuery)
            ->where('setting_key', 'workflow.approval_chains')
            ->where('status', 'active')
            ->orderByDesc('version')
            ->first();

        $backupDrSetting = (clone $settingQuery)
            ->where('setting_key', 'governance.backup_dr')
            ->where('status', 'active')
            ->orderByDesc('version')
            ->first();

        return [
            'source' => 'laravel-sqlite',
            'admin_users_index_url' => route('admin.users.index', [], false),
            'admin_users_store_url' => route('admin.users.store', [], false),
            'admin_roles_index_url' => route('admin.roles.index', [], false),
            'admin_roles_store_url' => route('admin.roles.store', [], false),
            'admin_role_update_url_template' => '/admin/roles/__ROLE__',
            'admin_companies_store_url' => route('admin.companies.store', [], false),
            'system_settings_index_url' => route('settings.system-settings.index', [], false),
            'system_settings_store_url' => route('settings.system-settings.store', [], false),
            'system_setting_approve_url_template' => '/settings/system-settings/__SETTING__/approve',
            'data_imports_index_url' => route('settings.data-imports.index', [], false),
            'data_imports_preview_url' => route('settings.data-imports.preview', [], false),
            'data_import_post_url_template' => '/settings/data-imports/__BATCH__/post',
            'can_view_users' => $canViewUsers,
            'can_manage_users' => $user->can('create', User::class),
            'can_view_roles' => $canViewRoles,
            'can_manage_roles' => $user->can('create', Role::class),
            'can_manage_companies' => $canManageCompanies,
            'can_view_settings' => $canViewSettings,
            'can_manage_settings' => $user->can('create', SystemSetting::class),
            'can_approve_settings' => $user->hasPermission('settings.approve'),
            'can_view_data_imports' => $canViewDataImports,
            'can_manage_data_imports' => $user->can('create', DataImportBatch::class),
            'data_import_types' => [
                ['value' => DataImportBatch::TYPE_CRM_PROSPECT_INQUIRIES, 'label' => 'CRM Prospect Inquiries'],
                ['value' => DataImportBatch::TYPE_HR_EMPLOYEES, 'label' => 'HR Employees'],
            ],
            'data_import_statuses' => [
                ['value' => DataImportBatch::STATUS_PREVIEW, 'label' => 'Preview'],
                ['value' => DataImportBatch::STATUS_POSTED, 'label' => 'Posted'],
                ['value' => DataImportBatch::STATUS_FAILED, 'label' => 'Failed'],
            ],
            'data_import_templates' => [
                DataImportBatch::TYPE_CRM_PROSPECT_INQUIRIES => [
                    'required_headers' => [
                        'project_code',
                        'name',
                        'email',
                        'phone',
                        'source',
                        'channel',
                        'preferred_contact_method',
                        'budget_min',
                        'budget_max',
                        'message',
                        'consent_to_contact',
                    ],
                    'sample_csv' => 'project_code,name,email,phone,source,channel,preferred_contact_method,budget_min,budget_max,message,consent_to_contact'."\n"
                        .'SKY-PUN,Sample Prospect,sample.prospect@example.test,+91 99000 10001,Website,website,phone,9000000,11500000,Interested in 2BHK,yes',
                ],
                DataImportBatch::TYPE_HR_EMPLOYEES => [
                    'required_headers' => [
                        'employee_code',
                        'name',
                        'designation',
                        'department',
                        'grade',
                        'employment_type',
                        'status',
                        'joined_on',
                        'statutory_state',
                        'branch_code',
                        'project_code',
                        'manager_employee_code',
                        'monthly_ctc',
                        'pan',
                        'aadhaar',
                        'uan',
                        'bank_account',
                    ],
                    'sample_csv' => 'employee_code,name,designation,department,grade,employment_type,status,joined_on,statutory_state,branch_code,project_code,manager_employee_code,monthly_ctc,pan,aadhaar,uan,bank_account'."\n"
                        .'EMP-IMPORT-001,Sample Employee,Site Engineer,Construction,B1,full_time,active,'.now()->subMonth()->toDateString().',MH,,,,65000,ABCDE1234F,123412341234,100200300400,123456789012',
                ],
            ],
            'data_import_max_file_size_kb' => 512,
            'users' => (clone $userQuery)
                ->latest()
                ->limit(40)
                ->get()
                ->map(fn (User $row): array => [
                    'id' => $row->id,
                    'name' => $row->name,
                    'email' => $row->email,
                    'status' => $row->status,
                    'role' => $row->role ? [
                        'slug' => $row->role->slug,
                        'name' => $row->role->name,
                        'scope_level' => $row->role->scope_level,
                    ] : null,
                    'company' => $row->company ? [
                        'id' => $row->company->id,
                        'code' => $row->company->code,
                        'name' => $row->company->name,
                    ] : null,
                    'last_active_label' => $row->updated_at?->diffForHumans(short: true) ?? 'not recorded',
                ])
                ->values()
                ->all(),
            'roles' => (clone $roleQuery)
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'scope_level', 'permissions', 'is_active'])
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'slug' => $role->slug,
                    'name' => $role->name,
                    'scope_level' => $role->scope_level,
                    'permissions_count' => count($role->permissions ?? []),
                    'permissions' => $role->permissions ?? [],
                    'users_count' => $role->users_count,
                    'is_active' => $role->is_active,
                ])
                ->values()
                ->all(),
            'modules' => $moduleQuery
                ->get(['id', 'slug', 'group_name', 'name', 'route', 'icon', 'required_permissions', 'is_active'])
                ->filter(fn (ErpModule $module): bool => $this->canSeeModule($user, $module))
                ->map(fn (ErpModule $module): array => [
                    'id' => $module->id,
                    'slug' => $module->slug,
                    'group_name' => $module->group_name,
                    'name' => $module->name,
                    'route' => $module->route,
                    'icon' => $module->icon,
                    'required_permissions' => $module->required_permissions ?? $this->defaultModulePermissions($module->slug),
                    'is_active' => $module->is_active,
                ])
                ->values()
                ->all(),
            'settings' => (clone $settingQuery)
                ->orderByRaw("case when status = 'draft' then 0 when status = 'active' then 1 else 2 end")
                ->orderBy('setting_group')
                ->orderBy('setting_key')
                ->limit(50)
                ->get()
                ->map(fn (SystemSetting $setting): array => $this->systemSettingBootstrapRow($setting))
                ->values()
                ->all(),
            'data_imports' => (clone $dataImportQuery)
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (DataImportBatch $batch): array => [
                    'id' => $batch->id,
                    'import_number' => $batch->import_number,
                    'import_type' => $batch->import_type,
                    'source_filename' => $batch->source_filename,
                    'status' => $batch->status,
                    'total_rows' => $batch->total_rows,
                    'valid_rows' => $batch->valid_rows,
                    'invalid_rows' => $batch->invalid_rows,
                    'preview_rows' => $batch->preview_rows ?? [],
                    'error_report' => $batch->error_report ?? [],
                    'reconciliation_summary' => $batch->reconciliation_summary ?? [],
                    'posted_at' => $batch->posted_at?->toISOString(),
                    'company' => $batch->company ? [
                        'id' => $batch->company->id,
                        'code' => $batch->company->code,
                        'name' => $batch->company->name,
                    ] : null,
                    'created_by' => $batch->createdBy ? [
                        'name' => $batch->createdBy->name,
                        'email' => $batch->createdBy->email,
                    ] : null,
                    'posted_by' => $batch->postedBy ? [
                        'name' => $batch->postedBy->name,
                        'email' => $batch->postedBy->email,
                    ] : null,
                    'created_at' => $batch->created_at?->toISOString(),
                    'updated_at' => $batch->updated_at?->toISOString(),
                ])
                ->values()
                ->all(),
            'approval_chains' => collect($workflowSetting?->value ?? [])
                ->map(fn ($steps, string $workflow): array => [
                    'workflow' => $workflow,
                    'steps' => array_values((array) $steps),
                ])
                ->values()
                ->all(),
            'backup_dr' => $backupDrSetting ? $this->systemSettingBootstrapRow($backupDrSetting) : null,
            'summary' => [
                'active_users' => (clone $userQuery)->where('status', 'active')->count(),
                'roles' => (clone $roleQuery)->count(),
                'active_modules' => $moduleQuery->count(),
                'active_settings' => (clone $settingQuery)->where('status', 'active')->count(),
                'draft_settings' => (clone $settingQuery)->where('status', 'draft')->count(),
                'data_import_batches' => (clone $dataImportQuery)->count(),
                'preview_imports' => (clone $dataImportQuery)->where('status', DataImportBatch::STATUS_PREVIEW)->count(),
                'approval_workflows' => collect($workflowSetting?->value ?? [])->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function authSecurityOptions(?User $user): ?array
    {
        if (! $user || $this->isPartnerPortalUser($user) || $this->isBuyerPortalUser($user)) {
            return null;
        }

        $canViewAudit = $user->hasPermission('*') || $user->hasPermission('audit.view');

        $authEventQuery = AuditEvent::query()
            ->where('event_type', 'like', 'auth.%')
            ->where('created_at', '>=', now()->subDays(30))
            ->when(! $canViewAudit, fn (Builder $query): Builder => $query->where('user_id', $user->id));

        $eventCounts = (clone $authEventQuery)
            ->get(['event_type'])
            ->countBy('event_type')
            ->map(fn (int $count, string $eventType): array => [
                'event_type' => $eventType,
                'count' => $count,
            ])
            ->values()
            ->all();

        $recentEvents = (clone $authEventQuery)
            ->with('user:id,name,email')
            ->latest()
            ->limit(8)
            ->get(['id', 'user_id', 'event_type', 'action', 'created_at', 'metadata'])
            ->map(fn (AuditEvent $event): array => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'description' => $event->action,
                'created_at' => $event->created_at?->toISOString(),
                'actor' => $event->user ? [
                    'id' => $event->user->id,
                    'name' => $event->user->name,
                    'email' => $event->user->email,
                ] : null,
                'outcome' => $event->metadata['outcome'] ?? null,
            ])
            ->values()
            ->all();

        return [
            'source' => 'laravel-auth',
            'login_route' => route('login', [], false),
            'forgot_password_route' => route('password.request', [], false),
            'password_reset_store_route' => route('password.store', [], false),
            'verification_notice_route' => route('verification.notice', [], false),
            'verification_resend_route' => route('verification.send', [], false),
            'logout_route' => route('logout', [], false),
            'can_view_audit_events' => $canViewAudit,
            'session' => [
                'driver' => (string) config('session.driver'),
                'secure_cookie' => (bool) config('session.secure'),
                'same_site' => (string) config('session.same_site'),
                'lifetime_minutes' => (int) config('session.lifetime'),
            ],
            'controls' => [
                [
                    'key' => 'laravel_session_auth',
                    'label' => 'Session authentication',
                    'status' => 'enabled',
                    'detail' => 'Login, logout and password reset use protected application routes.',
                ],
                [
                    'key' => 'account_status_guard',
                    'label' => 'Account status guard',
                    'status' => 'enabled',
                    'detail' => 'Inactive authenticated accounts are revoked by account.active middleware and audited.',
                ],
                [
                    'key' => 'email_verification',
                    'label' => 'Email verification',
                    'status' => 'enabled',
                    'detail' => 'Verified users are required for the ERP workspace route group.',
                ],
                [
                    'key' => 'auth_audit',
                    'label' => 'Authentication audit trail',
                    'status' => 'enabled',
                    'detail' => 'Login, logout, password reset and verification events are written without secret metadata.',
                ],
                [
                    'key' => 'security_headers',
                    'label' => 'Security headers',
                    'status' => 'enabled',
                    'detail' => 'HTML and JSON responses include baseline browser security headers.',
                ],
                [
                    'key' => 'otp_mfa',
                    'label' => 'OTP / MFA',
                    'status' => 'not_implemented',
                    'detail' => 'OTP and multi-factor authentication are not enabled for this delivery.',
                ],
            ],
            'event_counts' => $eventCounts,
            'recent_events' => $recentEvents,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function accountProfileOptions(?User $user, ?User $actor = null, ?string $selectedRoleSlug = null): ?array
    {
        if (! $user) {
            return null;
        }

        $role = $user->role;
        $company = $user->company;
        $permissions = is_array($role?->permissions) ? $role->permissions : [];

        $recentEvents = AuditEvent::query()
            ->where('user_id', $user->id)
            ->where('event_type', 'like', 'auth.%')
            ->latest()
            ->limit(8)
            ->get(['id', 'event_type', 'action', 'created_at', 'metadata'])
            ->map(fn (AuditEvent $event): array => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'description' => $event->action,
                'created_at' => $event->created_at?->toISOString(),
                'outcome' => $event->metadata['outcome'] ?? null,
            ])
            ->values()
            ->all();

        return [
            'source' => 'laravel-auth',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'email_verified' => $user->email_verified_at !== null,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
            ],
            'role' => [
                'id' => $role?->id,
                'name' => $role?->name,
                'slug' => $role?->slug,
                'scope_level' => $role?->scope_level,
                'permissions_count' => count($permissions),
                'has_wildcard_permission' => in_array('*', $permissions, true),
            ],
            'company' => $company ? [
                'id' => $company->id,
                'code' => $company->code,
                'name' => $company->name,
                'state' => $company->state,
                'status' => $company->status,
            ] : null,
            'active_role_context' => $this->activeRoleContext($actor, $user, $selectedRoleSlug),
            'active_project_context' => $this->activeProjectContext($user),
            'security' => [
                'session' => [
                    'driver' => (string) config('session.driver'),
                    'secure_cookie' => (bool) config('session.secure'),
                    'same_site' => (string) config('session.same_site'),
                    'lifetime_minutes' => (int) config('session.lifetime'),
                ],
                'forgot_password_route' => route('password.request', [], false),
                'logout_route' => route('logout', [], false),
            ],
            'recent_events' => $recentEvents,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function systemSettingBootstrapRow(SystemSetting $setting): array
    {
        return [
            'id' => $setting->id,
            'scope_key' => $setting->scope_key,
            'setting_group' => $setting->setting_group,
            'setting_key' => $setting->setting_key,
            'label' => $setting->label,
            'description' => $setting->description,
            'value_type' => $setting->value_type,
            'value' => $setting->value ?? [],
            'status' => $setting->status,
            'version' => $setting->version,
            'effective_from' => $setting->effective_from?->toDateString(),
            'effective_to' => $setting->effective_to?->toDateString(),
            'approved_at' => $setting->approved_at?->toISOString(),
            'workflow_history' => $setting->workflow_history ?? [],
            'company' => $setting->company ? [
                'id' => $setting->company->id,
                'code' => $setting->company->code,
                'name' => $setting->company->name,
            ] : null,
            'created_by' => $setting->createdBy ? [
                'id' => $setting->createdBy->id,
                'name' => $setting->createdBy->name,
                'email' => $setting->createdBy->email,
            ] : null,
            'approved_by' => $setting->approvedBy ? [
                'id' => $setting->approvedBy->id,
                'name' => $setting->approvedBy->name,
                'email' => $setting->approvedBy->email,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function governanceReportOptions(?User $user): ?array
    {
        if (! $user || ! $user->hasPermission('reports.view')) {
            return null;
        }

        return [
            'source' => 'laravel-sqlite',
            'management_summary_url' => route('governance.management-summary.show', [], false),
            'register_url' => route('governance.report-register.index', [], false),
            'report_pin_store_url' => route('governance.report-pins.store', [], false),
            'report_pin_delete_url_template' => '/governance/report-pins/__PIN__',
            'report_schedule_store_url' => route('governance.report-schedules.store', [], false),
            'report_schedule_archive_url_template' => '/governance/report-schedules/__SCHEDULE__/archive',
            'supported_reports' => [
                'bookings',
                'collections',
                'payroll',
                'service_tickets',
                'leads',
                'inventory_units',
                'stock_items',
                'stock_movements',
                'purchase_orders',
                'vendors',
                'construction_milestones',
                'daily_progress_reports',
                'rera_registrations',
                ...($user->hasPermission('audit.view') ? ['audit_events'] : []),
            ],
            'supported_report_statuses' => [
                'bookings' => [
                    ['value' => 'draft', 'label' => 'Draft'],
                    ['value' => 'confirmed', 'label' => 'Confirmed'],
                    ['value' => 'agreement_pending', 'label' => 'Agreement Pending'],
                    ['value' => 'registered', 'label' => 'Registered'],
                    ['value' => 'cancelled', 'label' => 'Cancelled'],
                ],
                'collections' => [
                    ['value' => 'submitted', 'label' => 'Submitted'],
                    ['value' => 'approved', 'label' => 'Approved'],
                    ['value' => 'rejected', 'label' => 'Rejected'],
                    ['value' => 'cancelled', 'label' => 'Cancelled'],
                ],
                'payroll' => [
                    ['value' => 'draft', 'label' => 'Draft'],
                    ['value' => 'generated', 'label' => 'Generated'],
                    ['value' => 'approved', 'label' => 'Approved'],
                    ['value' => 'rejected', 'label' => 'Rejected'],
                ],
                'service_tickets' => [
                    ['value' => 'open', 'label' => 'Open'],
                    ['value' => 'assigned', 'label' => 'Assigned'],
                    ['value' => 'in_progress', 'label' => 'In Progress'],
                    ['value' => 'resolved', 'label' => 'Resolved'],
                    ['value' => 'closed', 'label' => 'Closed'],
                ],
                'leads' => [
                    ['value' => 'open', 'label' => 'Open'],
                    ['value' => 'won', 'label' => 'Won'],
                    ['value' => 'lost', 'label' => 'Lost'],
                    ['value' => 'on_hold', 'label' => 'On Hold'],
                ],
                'inventory_units' => [
                    ['value' => 'available', 'label' => 'Available'],
                    ['value' => 'reserved', 'label' => 'Reserved'],
                    ['value' => 'booked', 'label' => 'Booked'],
                    ['value' => 'registered', 'label' => 'Registered'],
                    ['value' => 'handed_over', 'label' => 'Handed Over'],
                    ['value' => 'blocked', 'label' => 'Blocked'],
                    ['value' => 'on_hold', 'label' => 'On Hold'],
                ],
                'stock_items' => [
                    ['value' => 'active', 'label' => 'Active'],
                    ['value' => 'inactive', 'label' => 'Inactive'],
                ],
                'stock_movements' => [
                    ['value' => 'inward', 'label' => 'Inward'],
                    ['value' => 'issue', 'label' => 'Issue'],
                    ['value' => 'consumption', 'label' => 'Consumption'],
                    ['value' => 'wastage', 'label' => 'Wastage'],
                    ['value' => 'return', 'label' => 'Return'],
                    ['value' => 'transfer_out', 'label' => 'Transfer Out'],
                    ['value' => 'transfer_in', 'label' => 'Transfer In'],
                ],
                'purchase_orders' => [
                    ['value' => 'draft', 'label' => 'Draft'],
                    ['value' => 'approved', 'label' => 'Approved'],
                    ['value' => 'partially_received', 'label' => 'Partially Received'],
                    ['value' => 'received', 'label' => 'Received'],
                    ['value' => 'cancelled', 'label' => 'Cancelled'],
                ],
                'vendors' => [
                    ['value' => 'active', 'label' => 'Active'],
                    ['value' => 'inactive', 'label' => 'Inactive'],
                    ['value' => 'blocked', 'label' => 'Blocked'],
                ],
                'construction_milestones' => [
                    ['value' => 'planned', 'label' => 'Planned'],
                    ['value' => 'in_progress', 'label' => 'In Progress'],
                    ['value' => 'completed', 'label' => 'Completed'],
                    ['value' => 'delayed', 'label' => 'Delayed'],
                ],
                'daily_progress_reports' => [
                    ['value' => 'submitted', 'label' => 'Submitted'],
                    ['value' => 'approved', 'label' => 'Approved'],
                    ['value' => 'rejected', 'label' => 'Rejected'],
                ],
                'rera_registrations' => [
                    ['value' => 'submitted', 'label' => 'Submitted'],
                    ['value' => 'verified', 'label' => 'Verified'],
                ],
            ],
            'supported_formats' => ['json', 'csv', 'excel', 'pdf'],
            'schedule_frequencies' => ['daily', 'weekly', 'monthly'],
            'pinned_reports' => ReportPin::query()
                ->where('user_id', $user->id)
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (ReportPin $pin): array => [
                    'id' => $pin->id,
                    'report_key' => $pin->report_key,
                    'label' => $pin->label,
                    'filters' => $pin->filters ?? [],
                    'created_at' => $pin->created_at?->toISOString(),
                ])
                ->values()
                ->all(),
            'scheduled_reports' => ReportSchedule::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (ReportSchedule $schedule): array => [
                    'id' => $schedule->id,
                    'report_key' => $schedule->report_key,
                    'label' => $schedule->label,
                    'frequency' => $schedule->frequency,
                    'format' => $schedule->format,
                    'filters' => $schedule->filters ?? [],
                    'recipients' => $schedule->recipients ?? [],
                    'starts_on' => $schedule->starts_on?->toDateString(),
                    'ends_on' => $schedule->ends_on?->toDateString(),
                    'next_run_at' => $schedule->next_run_at?->toISOString(),
                    'status' => $schedule->status,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function auditTrailOptions(?User $user): ?array
    {
        if (! $user || ! $user->can('audit.view')) {
            return null;
        }

        return [
            'source' => 'laravel-sqlite',
            'index_url' => route('governance.audit-events.index', [], false),
            'export_url' => route('governance.audit-events.export', [], false),
            'supported_filters' => ['event_type', 'user_id', 'auditable_type', 'auditable_id', 'request_method', 'request_id', 'date_from', 'date_to', 'search', 'page'],
            'supported_exports' => ['csv'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function collaborationTaskOptions(?User $user): ?array
    {
        if (! $user || ! $user->can('viewAny', WorkTask::class)) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);
        $projectIds = $this->visibleProjectIds($user);

        $assignees = User::query()
            ->with('role:id,name,slug,permissions')
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->where('status', 'active')
            ->whereNotNull('company_id')
            ->orderBy('name')
            ->get(['id', 'company_id', 'role_id', 'name', 'email'])
            ->filter(function (User $assignee): bool {
                $permissions = $assignee->role?->permissions ?? [];

                return ! in_array('partner.portal', $permissions, true)
                    && ! in_array('buyer.view', $permissions, true);
            })
            ->map(fn (User $assignee) => [
                'id' => $assignee->id,
                'company_id' => $assignee->company_id,
                'name' => $assignee->name,
                'email' => $assignee->email,
                'role' => $assignee->role?->name,
            ])
            ->values()
            ->all();

        $projects = Project::query()
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('id', $projectIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'company_id', 'code', 'name'])
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'company_id' => $project->company_id,
                'code' => $project->code,
                'name' => $project->name,
            ])
            ->values()
            ->all();

        $taskSetting = SystemSetting::query()
            ->with(['company:id,code,name', 'createdBy:id,name,email', 'approvedBy:id,name,email'])
            ->where('setting_key', 'collaboration.task_settings')
            ->where('status', 'active')
            ->where(function (Builder $query) use ($companyIds): void {
                if (is_array($companyIds)) {
                    $query->whereIn('company_id', $companyIds ?: [0])
                        ->orWhereNull('company_id');

                    return;
                }

                $query->whereNull('company_id')
                    ->orWhereNotNull('company_id');
            })
            ->orderByRaw('case when company_id is null then 1 else 0 end')
            ->orderByDesc('version')
            ->first();

        return [
            'source' => 'laravel-sqlite',
            'index_url' => route('collaboration.tasks.index', ['scope' => 'mine'], false),
            'export_url' => route('collaboration.tasks.export', [], false),
            'store_url' => route('collaboration.tasks.store', [], false),
            'bulk_update_url' => route('collaboration.tasks.bulk-update', [], false),
            'bulk_archive_url' => route('collaboration.tasks.bulk-archive', [], false),
            'update_url_template' => '/collaboration/tasks/__TASK__',
            'assign_url_template' => '/collaboration/tasks/__TASK__/assign',
            'transfer_request_url_template' => '/collaboration/tasks/__TASK__/transfer-requests',
            'transfer_resolve_url_template' => '/collaboration/tasks/transfer-requests/__TRANSFER__/resolve',
            'archive_url_template' => '/collaboration/tasks/__TASK__/archive',
            'status_url_template' => '/collaboration/tasks/__TASK__/status',
            'watcher_url_template' => '/collaboration/tasks/__TASK__/watcher',
            'dependencies_url_template' => '/collaboration/tasks/__TASK__/dependencies',
            'comment_url_template' => '/collaboration/tasks/__TASK__/comments',
            'checklist_url_template' => '/collaboration/tasks/__TASK__/checklist',
            'subtask_url_template' => '/collaboration/tasks/__TASK__/subtasks',
            'subtask_status_url_template' => '/collaboration/tasks/__TASK__/subtasks/__SUBTASK__',
            'time_log_url_template' => '/collaboration/tasks/__TASK__/time-logs',
            'can_create' => $user->can('create', WorkTask::class),
            'can_manage' => $user->hasPermission('collaboration.manage'),
            'can_manage_settings' => $user->can('create', SystemSetting::class),
            'permission_summary' => [
                [
                    'role' => $user->role?->name ?? 'Current role',
                    'view' => $user->can('viewAny', WorkTask::class),
                    'create' => $user->can('create', WorkTask::class),
                    'manage' => $user->hasPermission('collaboration.manage'),
                    'settings' => $user->can('create', SystemSetting::class),
                    'export' => $user->can('viewAny', WorkTask::class),
                ],
            ],
            'system_settings_store_url' => route('settings.system-settings.store', [], false),
            'task_settings' => $taskSetting ? $this->systemSettingBootstrapRow($taskSetting) : null,
            'task_settings_key' => 'collaboration.task_settings',
            'current_user_id' => $user->id,
            'assignees' => $assignees,
            'projects' => $projects,
            'statuses' => ['open', 'in_progress', 'blocked', 'completed', 'cancelled'],
            'priorities' => ['low', 'medium', 'high', 'critical'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function collaborationCalendarOptions(?User $user): ?array
    {
        if (! $user || ! $user->can('viewAny', CalendarEvent::class)) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);
        $projectIds = $this->visibleProjectIds($user);

        $assignees = User::query()
            ->with('role:id,name,slug,permissions')
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->where('status', 'active')
            ->whereNotNull('company_id')
            ->orderBy('name')
            ->get(['id', 'company_id', 'role_id', 'name', 'email'])
            ->filter(function (User $assignee): bool {
                $permissions = $assignee->role?->permissions ?? [];

                return ! in_array('partner.portal', $permissions, true)
                    && ! in_array('buyer.view', $permissions, true);
            })
            ->map(fn (User $assignee) => [
                'id' => $assignee->id,
                'company_id' => $assignee->company_id,
                'name' => $assignee->name,
                'email' => $assignee->email,
                'role' => $assignee->role?->name,
            ])
            ->values()
            ->all();

        $projects = Project::query()
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('id', $projectIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'company_id', 'code', 'name'])
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'company_id' => $project->company_id,
                'code' => $project->code,
                'name' => $project->name,
            ])
            ->values()
            ->all();

        return [
            'source' => 'laravel-sqlite',
            'index_url' => route('collaboration.calendar-events.index', [], false),
            'store_url' => route('collaboration.calendar-events.store', [], false),
            'update_url_template' => '/collaboration/calendar-events/__EVENT__',
            'complete_url_template' => '/collaboration/calendar-events/__EVENT__/complete',
            'cancel_url_template' => '/collaboration/calendar-events/__EVENT__/cancel',
            'delete_url_template' => '/collaboration/calendar-events/__EVENT__',
            'can_create' => $user->can('create', CalendarEvent::class),
            'can_manage' => $user->hasPermission('collaboration.manage'),
            'can_delete' => $user->hasPermission('collaboration.manage'),
            'current_user_id' => $user->id,
            'assignees' => $assignees,
            'projects' => $projects,
            'event_types' => ['meeting', 'site_visit', 'interview', 'payment_follow_up', 'inspection', 'training'],
            'statuses' => ['scheduled', 'rescheduled', 'completed', 'cancelled'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function chatConnectOptions(?User $user): ?array
    {
        if (! $user || ! app(ChatAccessService::class)->canView($user)) {
            return null;
        }

        $access = app(ChatAccessService::class);
        $capabilities = $access->capabilitiesFor($user);
        $mailboxOptions = $this->collaborationMailboxOptions($user) ?? [];
        $recipients = $this->chatConnectRecipients($user, $access, $mailboxOptions['recipients'] ?? []);
        $projects = ! empty($mailboxOptions['projects'])
            ? $mailboxOptions['projects']
            : $this->chatConnectProjects($user);

        return array_merge($mailboxOptions, [
            'source' => 'current-records',
            'enabled' => true,
            'read_only' => (bool) ($capabilities['read_only'] ?? false),
            'role_access' => $access->roleAccessMatrix($user->company_id),
            'capabilities' => [
                'create_dm' => (bool) ($capabilities['can_create_dm'] ?? false),
                'create_group' => (bool) ($capabilities['can_create_group'] ?? false),
                'create_channel' => (bool) ($capabilities['can_create_channel'] ?? false),
                'upload' => (bool) ($capabilities['can_upload'] ?? false),
                'voice' => (bool) ($capabilities['can_send_voice'] ?? false),
                'poll' => (bool) ($capabilities['can_create_poll'] ?? false),
                'vote_poll' => (bool) ($capabilities['can_vote_poll'] ?? false),
                'manage_members' => (bool) ($capabilities['can_manage_members'] ?? false),
                'archive' => (bool) ($capabilities['can_archive'] ?? false),
                'export' => (bool) ($capabilities['can_export'] ?? false),
                'realtime' => (bool) env('REVERB_APP_KEY'),
            ],
            'can_create' => ! (bool) ($capabilities['read_only'] ?? false)
                && (
                    (bool) ($capabilities['can_create_dm'] ?? false)
                    || (bool) ($capabilities['can_create_group'] ?? false)
                    || (bool) ($capabilities['can_create_channel'] ?? false)
                ),
            'can_post' => (bool) ($capabilities['can_post'] ?? false),
            'can_create_dm' => (bool) ($capabilities['can_create_dm'] ?? false),
            'can_create_group' => (bool) ($capabilities['can_create_group'] ?? false),
            'can_create_channel' => (bool) ($capabilities['can_create_channel'] ?? false),
            'can_upload' => (bool) ($capabilities['can_upload'] ?? false),
            'can_send_voice' => (bool) ($capabilities['can_send_voice'] ?? false),
            'can_create_poll' => (bool) ($capabilities['can_create_poll'] ?? false),
            'can_vote_poll' => (bool) ($capabilities['can_vote_poll'] ?? false),
            'conversation_index_url' => route('collaboration.chat.conversations.index', [], false),
            'conversation_store_url' => route('collaboration.chat.conversations.store', [], false),
            'message_index_url_template' => '/collaboration/chat/conversations/__CONVERSATION__/messages',
            'message_store_url_template' => '/collaboration/chat/conversations/__CONVERSATION__/messages',
            'attachment_download_url_template' => '/collaboration/chat/attachments/__ATTACHMENT__/download',
            'attachment_preview_url_template' => '/collaboration/chat/attachments/__ATTACHMENT__/preview',
            'reaction_url_template' => '/collaboration/chat/messages/__MESSAGE__/reactions',
            'poll_store_url_template' => '/collaboration/chat/conversations/__CONVERSATION__/polls',
            'poll_vote_url_template' => '/collaboration/chat/polls/__POLL__/votes',
            'poll_close_url_template' => '/collaboration/chat/polls/__POLL__/close',
            'conversation_messages_url_template' => '/collaboration/chat/conversations/__CONVERSATION__/messages',
            'conversation_message_store_url_template' => '/collaboration/chat/conversations/__CONVERSATION__/messages',
            'conversation_read_url_template' => '/collaboration/chat/conversations/__CONVERSATION__/read',
            'conversation_archive_url_template' => '/collaboration/chat/conversations/__CONVERSATION__/archive',
            'current_user_id' => $user->id,
            'recipients' => $recipients,
            'projects' => $projects,
            'conversation_types' => [
                ['value' => 'direct_message', 'label' => 'Direct Message'],
                ['value' => 'group_chat', 'label' => 'Group Chat'],
                ['value' => 'project_channel', 'label' => 'Project Channel'],
                ['value' => 'unit_conversation', 'label' => 'Unit Conversation'],
                ['value' => 'lead_conversation', 'label' => 'Lead Conversation'],
                ['value' => 'approval_thread', 'label' => 'Approval Thread'],
                ['value' => 'voucher_thread', 'label' => 'Voucher Thread'],
                ['value' => 'task_thread', 'label' => 'Task Thread'],
                ['value' => 'announcement_channel', 'label' => 'Announcement Channel'],
            ],
            'internal_only' => true,
            'reverb' => [
                'enabled' => (bool) env('REVERB_APP_KEY'),
                'key' => env('REVERB_APP_KEY'),
                'host' => env('REVERB_HOST', '127.0.0.1'),
                'port' => (int) env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'channel_prefix' => 'chat.conversation.',
            ],
            'read_only_message' => 'Chat Connect is temporarily unavailable because your session could not be verified. Reconnect or sign in again to continue messaging.',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $mailboxRecipients
     * @return array<int, array<string, mixed>>
     */
    private function chatConnectRecipients(User $user, ChatAccessService $access, array $mailboxRecipients = []): array
    {
        if ($mailboxRecipients !== []) {
            $recipientIds = collect($mailboxRecipients)
                ->pluck('id')
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            $allowedIds = User::query()
                ->with('role')
                ->whereIn('id', $recipientIds ?: [0])
                ->get()
                ->filter(fn (User $recipient): bool => $access->canView($recipient))
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            return collect($mailboxRecipients)
                ->filter(fn (array $recipient): bool => in_array((int) ($recipient['id'] ?? 0), $allowedIds, true))
                ->values()
                ->all();
        }

        $companyIds = $this->visibleCompanyIds($user);

        return User::query()
            ->with('role:id,name,slug,permissions')
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->where('status', 'active')
            ->whereNotNull('company_id')
            ->whereKeyNot($user->id)
            ->orderBy('name')
            ->get(['id', 'company_id', 'role_id', 'name', 'email'])
            ->filter(fn (User $recipient): bool => $access->canView($recipient))
            ->map(fn (User $recipient): array => [
                'id' => $recipient->id,
                'company_id' => $recipient->company_id,
                'name' => $recipient->name,
                'email' => $recipient->email,
                'role' => $recipient->role?->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function chatConnectProjects(User $user): array
    {
        $projectIds = $this->visibleProjectIds($user);

        return Project::query()
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('id', $projectIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'company_id', 'code', 'name'])
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'company_id' => $project->company_id,
                'code' => $project->code,
                'name' => $project->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function collaborationMailboxOptions(?User $user): ?array
    {
        if (! $user || ! $user->can('viewAny', CollaborationMessage::class)) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);
        $projectIds = $this->visibleProjectIds($user);

        $mailboxSetting = SystemSetting::query()
            ->with(['company:id,code,name', 'createdBy:id,name,email', 'approvedBy:id,name,email'])
            ->where('setting_key', 'collaboration.mailbox_settings')
            ->where('status', 'active')
            ->where(function (Builder $query) use ($companyIds): void {
                if (is_array($companyIds)) {
                    $query->whereIn('company_id', $companyIds ?: [0])
                        ->orWhereNull('company_id');

                    return;
                }

                $query->whereNull('company_id')
                    ->orWhereNotNull('company_id');
            })
            ->orderByRaw('case when company_id is null then 1 else 0 end')
            ->orderByDesc('version')
            ->first();

        $recipients = User::query()
            ->with('role:id,name,slug,permissions')
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->where('status', 'active')
            ->whereNotNull('company_id')
            ->whereKeyNot($user->id)
            ->orderBy('name')
            ->get(['id', 'company_id', 'role_id', 'name', 'email'])
            ->filter(function (User $recipient): bool {
                $permissions = $recipient->role?->permissions ?? [];

                return ! in_array('partner.portal', $permissions, true)
                    && ! in_array('buyer.view', $permissions, true);
            })
            ->map(fn (User $recipient) => [
                'id' => $recipient->id,
                'company_id' => $recipient->company_id,
                'name' => $recipient->name,
                'email' => $recipient->email,
                'role' => $recipient->role?->name,
            ])
            ->values()
            ->all();

        $projects = Project::query()
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('id', $projectIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'company_id', 'code', 'name'])
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'company_id' => $project->company_id,
                'code' => $project->code,
                'name' => $project->name,
            ])
            ->values()
            ->all();

        $leads = Lead::query()
            ->with(['customer:id,code,name', 'project:id,code,name'])
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]))
            ->latest()
            ->limit(25)
            ->get(['id', 'company_id', 'project_id', 'customer_id', 'lead_code', 'stage', 'status'])
            ->map(fn (Lead $lead): array => [
                'type' => 'lead',
                'id' => $lead->id,
                'company_id' => $lead->company_id,
                'label' => trim($lead->lead_code.' · '.($lead->customer?->name ?? 'Lead'), ' ·'),
                'meta' => trim(($lead->project?->code ?? 'No project').' · '.$lead->stage.' · '.$lead->status, ' ·'),
            ])
            ->values()
            ->all();

        $bookings = Booking::query()
            ->with(['customer:id,code,name', 'project:id,code,name'])
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]))
            ->latest('booked_on')
            ->limit(25)
            ->get(['id', 'company_id', 'project_id', 'customer_id', 'booking_code', 'status', 'net_receivable'])
            ->map(fn (Booking $booking): array => [
                'type' => 'booking',
                'id' => $booking->id,
                'company_id' => $booking->company_id,
                'label' => implode(' · ', array_filter([$booking->booking_code, $booking->customer?->name])),
                'meta' => trim(($booking->project?->code ?? 'No project').' · '.$booking->status.' · ₹'.number_format((float) $booking->net_receivable, 0, '.', ','), ' ·'),
            ])
            ->values()
            ->all();

        $customers = Customer::query()
            ->where(function (Builder $query) use ($companyIds, $projectIds): void {
                $query->whereHas('bookings', function (Builder $bookingQuery) use ($companyIds, $projectIds): void {
                    $bookingQuery
                        ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
                        ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]));
                })->orWhereHas('leads', function (Builder $leadQuery) use ($companyIds, $projectIds): void {
                    $leadQuery
                        ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
                        ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]));
                });
            })
            ->orderBy('name')
            ->limit(25)
            ->get(['id', 'code', 'name', 'email', 'phone', 'status'])
            ->map(fn (Customer $customer): array => [
                'type' => 'customer',
                'id' => $customer->id,
                'company_id' => null,
                'label' => trim(($customer->code ? $customer->code.' · ' : '').$customer->name),
                'meta' => trim(($customer->email ?: $customer->phone ?: 'Customer').' · '.$customer->status, ' ·'),
            ])
            ->values()
            ->all();

        return [
            'source' => 'current-records',
            'index_url' => route('collaboration.messages.index', [], false),
            'export_url' => route('collaboration.messages.export', [], false),
            'store_url' => route('collaboration.messages.store', [], false),
            'read_url_template' => '/collaboration/messages/__MESSAGE__/read',
            'archive_url_template' => '/collaboration/messages/__MESSAGE__/archive',
            'cancel_scheduled_url_template' => '/collaboration/messages/__MESSAGE__/cancel-scheduled',
            'crm_link_url_template' => '/collaboration/messages/__MESSAGE__/crm-link',
            'state_url_template' => '/collaboration/messages/__MESSAGE__/state',
            'reaction_url_template' => '/collaboration/messages/__MESSAGE__/reactions',
            'lead_activity_store_url' => route('crm.lead-activities.store', [], false),
            'can_create' => $user->can('create', CollaborationMessage::class),
            'can_schedule_send' => $user->can('create', CollaborationMessage::class),
            'can_manage' => $user->hasPermission('collaboration.manage'),
            'can_link_crm' => ! $this->isPartnerPortalUser($user) && ! $this->isBuyerPortalUser($user),
            'can_update_state' => ! $this->isPartnerPortalUser($user) && ! $this->isBuyerPortalUser($user),
            'can_react' => ! $this->isPartnerPortalUser($user) && ! $this->isBuyerPortalUser($user),
            'can_create_lead_activity' => $user->can('create', LeadActivity::class),
            'can_manage_settings' => $user->can('create', SystemSetting::class),
            'system_settings_store_url' => route('settings.system-settings.store', [], false),
            'mailbox_settings' => $mailboxSetting ? $this->systemSettingBootstrapRow($mailboxSetting) : null,
            'mailbox_settings_key' => 'collaboration.mailbox_settings',
            'current_user_id' => $user->id,
            'recipients' => $recipients,
            'projects' => $projects,
            'crm_link_records' => [
                'projects' => array_map(fn (array $project): array => [
                    'type' => 'project',
                    'id' => $project['id'],
                    'company_id' => $project['company_id'],
                    'label' => trim(($project['code'] ?? '').' · '.($project['name'] ?? ''), ' ·'),
                    'meta' => 'Project master',
                ], $projects),
                'leads' => $leads,
                'bookings' => $bookings,
                'customers' => $customers,
            ],
            'folders' => ['inbox', 'sent', 'all'],
            'statuses' => ['unread', 'read', 'archived', 'scheduled', 'cancelled'],
            'priorities' => ['low', 'normal', 'high', 'critical'],
            'export_formats' => ['csv', 'pdf'],
            'chat_poll_interval_seconds' => 15,
            'mailbox_refresh_interval_seconds' => 30,
        ];
    }

    /** @return array<string, mixed>|null */
    private function companyMailboxOptions(?User $user): ?array
    {
        if (! $user || ! $user->can('viewAny', MailboxAccount::class)) {
            return null;
        }

        $accounts = MailboxAccount::query()
            ->accessibleTo($user)
            ->with(['assignments' => fn ($query) => $query->where('user_id', $user->id)])
            ->orderBy('name')
            ->get()
            ->map(function (MailboxAccount $account) use ($user): array {
                $assignment = $account->assignments->first();
                $isOwner = (int) $account->user_id === (int) $user->id;

                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'email' => $account->email,
                    'status' => $account->status,
                    'can_send' => $isOwner || (bool) $assignment?->can_send,
                    'can_manage' => $isOwner || (bool) $assignment?->can_manage,
                    'is_default' => (bool) $assignment?->is_default,
                    'workspace_url' => route('mailbox.external.show', $account, false),
                ];
            })
            ->values()
            ->all();

        return [
            'source' => 'company-email',
            'index_url' => route('mailbox.index', [], false),
            'accounts_url' => route('mailbox.accounts.index', [], false),
            'can_connect' => $user->can('create', MailboxAccount::class),
            'accounts' => $accounts,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function financeVoucherOptions(?User $user): ?array
    {
        if (! $user || ! $user->can('viewAny', FinancialVoucher::class)) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);
        $projectIds = $this->visibleProjectIds($user);

        $companies = Company::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('id', $companyIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Company $company): array => [
                'id' => $company->id,
                'code' => $company->code,
                'name' => $company->name,
            ])
            ->values()
            ->all();

        $projects = Project::query()
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('id', $projectIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'company_id', 'code', 'name'])
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'company_id' => $project->company_id,
                'code' => $project->code,
                'name' => $project->name,
            ])
            ->values()
            ->all();

        return [
            'source' => 'laravel-sqlite',
            'index_url' => route('finance.vouchers.index', [], false),
            'store_url' => route('finance.vouchers.store', [], false),
            'can_create' => $user->can('create', FinancialVoucher::class),
            'can_approve' => $user->hasPermission('finance.approve'),
            'companies' => $companies,
            'projects' => $projects,
            'voucher_types' => [
                ['value' => 'payment', 'label' => 'Payment'],
                ['value' => 'receipt', 'label' => 'Receipt'],
                ['value' => 'journal', 'label' => 'Journal'],
                ['value' => 'contra', 'label' => 'Contra'],
                ['value' => 'debit_note', 'label' => 'Debit Note'],
                ['value' => 'credit_note', 'label' => 'Credit Note'],
            ],
            'default_accounts' => [
                'payment' => [
                    'debit' => ['code' => 'SITE-EXP', 'name' => 'Site Expense'],
                    'credit' => ['code' => 'BANK-HDFC-001', 'name' => 'HDFC Bank Collection Account'],
                ],
                'receipt' => [
                    'debit' => ['code' => 'BANK-HDFC-001', 'name' => 'HDFC Bank Collection Account'],
                    'credit' => ['code' => 'CUSTOMER-COLLECTION', 'name' => 'Customer Collection'],
                ],
                'journal' => [
                    'debit' => ['code' => 'ADJUSTMENT-DR', 'name' => 'Adjustment Debit'],
                    'credit' => ['code' => 'ADJUSTMENT-CR', 'name' => 'Adjustment Credit'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function financeDashboard(?User $user): ?array
    {
        if (! $user || ! $this->canViewFinanceDashboard($user)) {
            return null;
        }

        $dashboard = app(FinanceDashboardService::class)->dashboard($user, []);

        return array_merge($dashboard, [
            'index_url' => route('finance.dashboard', [], false),
        ]);
    }

    private function canViewFinanceDashboard(User $user): bool
    {
        return $user->hasPermission('finance.view')
            || $user->hasPermission('finance.manage')
            || $user->hasPermission('finance.approve')
            || $user->hasPermission('collections.view')
            || $user->hasPermission('collections.manage')
            || $user->hasPermission('collections.approve')
            || $user->hasPermission('reports.view');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function financePaymentRequestOptions(?User $user): ?array
    {
        if (! $user || $user->hasPermission('buyer.view') || ! $user->can('viewAny', PaymentRequest::class)) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);
        $projectIds = $this->visibleProjectIds($user);

        $requests = PaymentRequest::query()
            ->with(['project:id,code,name', 'booking:id,booking_code,status,net_receivable', 'customer:id,code,name,email', 'paymentSchedule:id,sequence,milestone,amount,due_on,status'])
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]))
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (PaymentRequest $request): array => $this->paymentRequestBootstrapRow($request))
            ->values()
            ->all();

        $bookings = Booking::query()
            ->with([
                'project:id,code,name',
                'customer:id,code,name,email',
                'paymentSchedules.collectionReceipts' => fn ($query) => $query->whereIn('status', ['submitted', 'approved']),
                'paymentSchedules.paymentRequests' => fn ($query) => $query->where('status', 'requested'),
                'collectionReceipts' => fn ($query) => $query->whereIn('status', ['submitted', 'approved']),
            ])
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]))
            ->whereIn('status', ['confirmed', 'agreement_pending', 'registered'])
            ->latest('booked_on')
            ->limit(25)
            ->get()
            ->map(fn (Booking $booking): array => $this->paymentRequestBookingBootstrapRow($booking))
            ->filter(fn (array $booking): bool => $booking['outstanding_amount'] > 0)
            ->values()
            ->all();

        $gatewayProvider = (string) config('builder360.integrations.payment_gateway.provider', 'prototype');

        return [
            'source' => 'laravel-sqlite',
            'index_url' => route('finance.payment-requests.index', [], false),
            'store_url' => route('finance.payment-requests.store', [], false),
            'cancel_url_template' => '/finance/payment-requests/__PAYMENT_REQUEST__/cancel',
            'can_create' => $user->can('create', PaymentRequest::class),
            'can_cancel' => $user->hasPermission('collections.manage'),
            'gateway_provider' => $gatewayProvider,
            'gateway_mode' => $this->paymentGatewayMode($gatewayProvider),
            'gateway_label' => $this->paymentGatewayLabel($gatewayProvider),
            'statuses' => ['requested', 'paid', 'cancelled', 'expired', 'failed'],
            'default_expiry_days' => 7,
            'requests' => $requests,
            'bookings' => $bookings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentRequestBootstrapRow(PaymentRequest $request): array
    {
        return [
            'id' => $request->id,
            'request_number' => $request->request_number,
            'status' => $request->status,
            'amount' => (float) $request->amount,
            'currency' => $request->currency,
            'purpose' => $request->purpose,
            'expires_at' => $request->expires_at?->toISOString(),
            'payment_url' => $request->gateway_payload['payment_url'] ?? null,
            'project' => $request->project ? [
                'id' => $request->project->id,
                'code' => $request->project->code,
                'name' => $request->project->name,
            ] : null,
            'booking' => $request->booking ? [
                'id' => $request->booking->id,
                'booking_code' => $request->booking->booking_code,
                'status' => $request->booking->status,
            ] : null,
            'payment_schedule' => $request->paymentSchedule ? [
                'id' => $request->paymentSchedule->id,
                'sequence' => (int) $request->paymentSchedule->sequence,
                'milestone' => $request->paymentSchedule->milestone,
                'amount' => (float) $request->paymentSchedule->amount,
                'due_on' => $request->paymentSchedule->due_on?->toDateString(),
                'status' => $request->paymentSchedule->status,
            ] : null,
            'customer' => $request->customer ? [
                'id' => $request->customer->id,
                'code' => $request->customer->code,
                'name' => $request->customer->name,
                'email' => $request->customer->email,
            ] : null,
            'created_at' => $request->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentRequestBookingBootstrapRow(Booking $booking): array
    {
        $paidAgainstBooking = (float) $booking->collectionReceipts->sum('amount');
        $outstanding = max((float) $booking->net_receivable - $paidAgainstBooking, 0);

        return [
            'id' => $booking->id,
            'booking_code' => $booking->booking_code,
            'status' => $booking->status,
            'net_receivable' => (float) $booking->net_receivable,
            'paid_amount' => round($paidAgainstBooking, 2),
            'outstanding_amount' => round($outstanding, 2),
            'project' => $booking->project ? [
                'id' => $booking->project->id,
                'code' => $booking->project->code,
                'name' => $booking->project->name,
            ] : null,
            'customer' => $booking->customer ? [
                'id' => $booking->customer->id,
                'code' => $booking->customer->code,
                'name' => $booking->customer->name,
                'email' => $booking->customer->email,
            ] : null,
            'schedules' => $booking->paymentSchedules
                ->map(function (BookingPaymentSchedule $schedule): array {
                    $paidAgainstSchedule = (float) $schedule->collectionReceipts->sum('amount');
                    $scheduleOutstanding = max((float) $schedule->amount - $paidAgainstSchedule, 0);

                    return [
                        'id' => $schedule->id,
                        'sequence' => (int) $schedule->sequence,
                        'milestone' => $schedule->milestone,
                        'amount' => (float) $schedule->amount,
                        'paid_amount' => round($paidAgainstSchedule, 2),
                        'outstanding_amount' => round($scheduleOutstanding, 2),
                        'due_on' => $schedule->due_on?->toDateString(),
                        'status' => $schedule->status,
                        'has_active_request' => $schedule->paymentRequests->isNotEmpty(),
                    ];
                })
                ->filter(fn (array $schedule): bool => $schedule['outstanding_amount'] > 0)
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function possessionHandoverOptions(?User $user): ?array
    {
        if (! $user || ! $user->can('viewAny', PossessionHandover::class)) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);
        $projectIds = $this->visibleProjectIds($user);

        $bookings = Booking::query()
            ->with([
                'project:id,company_id,code,name',
                'unit:id,company_id,project_id,unit_code,unit_number,status',
                'customer:id,code,name,email,phone',
            ])
            ->withSum([
                'collectionReceipts as approved_collection_total' => fn (Builder $query) => $query->where('status', 'approved'),
            ], 'amount')
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]))
            ->where('status', 'confirmed')
            ->whereNotIn('id', PossessionHandover::query()->select('booking_id'))
            ->latest('booked_on')
            ->limit(50)
            ->get()
            ->map(function (Booking $booking): array {
                $approvedCollections = (float) ($booking->approved_collection_total ?? 0);
                $netReceivable = (float) $booking->net_receivable;
                $outstanding = round(max($netReceivable - $approvedCollections, 0), 2);

                return [
                    'id' => $booking->id,
                    'company_id' => $booking->company_id,
                    'project_id' => $booking->project_id,
                    'project_unit_id' => $booking->project_unit_id,
                    'customer_id' => $booking->customer_id,
                    'booking_code' => $booking->booking_code,
                    'status' => $booking->status,
                    'booked_on' => $booking->booked_on?->toDateString(),
                    'net_receivable' => $netReceivable,
                    'approved_collections' => round($approvedCollections, 2),
                    'financial_outstanding' => $outstanding,
                    'project' => $booking->project ? [
                        'id' => $booking->project->id,
                        'code' => $booking->project->code,
                        'name' => $booking->project->name,
                    ] : null,
                    'unit' => $booking->unit ? [
                        'id' => $booking->unit->id,
                        'unit_code' => $booking->unit->unit_code,
                        'unit_number' => $booking->unit->unit_number,
                        'status' => $booking->unit->status,
                    ] : null,
                    'customer' => $booking->customer ? [
                        'id' => $booking->customer->id,
                        'code' => $booking->customer->code,
                        'name' => $booking->customer->name,
                        'email' => $booking->customer->email,
                        'phone' => $booking->customer->phone,
                    ] : null,
                ];
            })
            ->values()
            ->all();

        $handoverBaseQuery = PossessionHandover::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]));

        $handoverRows = (clone $handoverBaseQuery)
            ->with([
                'booking:id,booking_code,status,net_receivable',
                'project:id,company_id,code,name',
                'unit:id,company_id,project_id,unit_code,unit_number,status',
                'customer:id,code,name,email,phone',
            ])
            ->withCount([
                'snags as open_snags_count' => fn (Builder $query) => $query->where('status', 'open'),
            ])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (PossessionHandover $handover): array => [
                'id' => $handover->id,
                'handover_number' => $handover->handover_number,
                'booking_id' => $handover->booking_id,
                'booking_code' => $handover->booking?->booking_code,
                'target_handover_on' => $handover->target_handover_on?->toDateString(),
                'actual_handover_on' => $handover->actual_handover_on?->toDateString(),
                'status' => $handover->status,
                'financial_outstanding' => (float) $handover->financial_outstanding,
                'open_snags_count' => (int) ($handover->open_snags_count ?? 0),
                'checklist' => $handover->checklist ?? [],
                'blockers' => $handover->blockers ?? [],
                'possession_letter_reference' => $handover->possession_letter_reference,
                'project' => $handover->project ? [
                    'id' => $handover->project->id,
                    'code' => $handover->project->code,
                    'name' => $handover->project->name,
                ] : null,
                'unit' => $handover->unit ? [
                    'id' => $handover->unit->id,
                    'unit_code' => $handover->unit->unit_code,
                    'unit_number' => $handover->unit->unit_number,
                    'status' => $handover->unit->status,
                ] : null,
                'customer' => $handover->customer ? [
                    'id' => $handover->customer->id,
                    'code' => $handover->customer->code,
                    'name' => $handover->customer->name,
                    'email' => $handover->customer->email,
                    'phone' => $handover->customer->phone,
                ] : null,
            ])
            ->values()
            ->all();

        $openSnags = HandoverSnag::query()
            ->where('status', 'open')
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->whereHas('handover', fn (Builder $query) => $query
                ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0])))
            ->count();

        $snagRows = HandoverSnag::query()
            ->with([
                'handover:id,handover_number,status,project_unit_id,customer_id',
                'handover.unit:id,unit_code,unit_number',
                'handover.customer:id,name',
                'reportedBy:id,name',
                'resolvedBy:id,name',
            ])
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->whereHas('handover', fn (Builder $query) => $query
                ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0])))
            ->latest()
            ->limit(75)
            ->get()
            ->map(fn (HandoverSnag $snag): array => [
                'id' => $snag->id,
                'snag_number' => $snag->snag_number,
                'possession_handover_id' => $snag->possession_handover_id,
                'area' => $snag->area,
                'category' => $snag->category,
                'severity' => $snag->severity,
                'description' => $snag->description,
                'status' => $snag->status,
                'target_resolution_on' => $snag->target_resolution_on?->toDateString(),
                'resolved_at' => $snag->resolved_at?->toISOString(),
                'resolution_notes' => $snag->resolution_notes,
                'reported_by' => $snag->reportedBy ? [
                    'id' => $snag->reportedBy->id,
                    'name' => $snag->reportedBy->name,
                ] : null,
                'resolved_by' => $snag->resolvedBy ? [
                    'id' => $snag->resolvedBy->id,
                    'name' => $snag->resolvedBy->name,
                ] : null,
                'handover' => $snag->handover ? [
                    'id' => $snag->handover->id,
                    'handover_number' => $snag->handover->handover_number,
                    'status' => $snag->handover->status,
                    'unit' => $snag->handover->unit ? [
                        'unit_code' => $snag->handover->unit->unit_code,
                        'unit_number' => $snag->handover->unit->unit_number,
                    ] : null,
                    'customer' => $snag->handover->customer ? [
                        'name' => $snag->handover->customer->name,
                    ] : null,
                ] : null,
            ])
            ->values()
            ->all();

        $summary = [
            'eligible_bookings' => count($bookings),
            'ready_handovers' => (int) (clone $handoverBaseQuery)->where('status', 'ready')->count(),
            'completed_handovers' => (int) (clone $handoverBaseQuery)->where('status', 'completed')->count(),
            'payment_pending' => (int) (clone $handoverBaseQuery)->where('financial_outstanding', '>', 0)->count(),
            'open_snags' => (int) $openSnags,
            'total_handovers' => (int) (clone $handoverBaseQuery)->count(),
        ];

        return [
            'source' => 'laravel-sqlite',
            'index_url' => route('possession.handovers.index', [], false),
            'store_url' => route('possession.handovers.store', [], false),
            'letter_url_template' => '/possession/handovers/__HANDOVER__/letter',
            'complete_url_template' => '/possession/handovers/__HANDOVER__/complete',
            'snags_store_url' => route('possession.snags.store', [], false),
            'snag_resolve_url_template' => '/possession/snags/__SNAG__/resolve',
            'can_create' => $user->can('create', PossessionHandover::class),
            'can_issue_letter' => $user->hasPermission('possession.manage'),
            'can_complete' => $user->hasPermission('possession.approve'),
            'can_report_snag' => $user->can('create', HandoverSnag::class),
            'can_resolve_snag' => $user->hasPermission('possession.manage'),
            'bookings' => $bookings,
            'handovers' => $handoverRows,
            'snags' => $snagRows,
            'summary' => $summary,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function crmLeadRows(?User $user): array
    {
        if (! $user || (! $user->hasPermission('crm.view') && ! $user->hasPermission('crm.manage'))) {
            return [];
        }

        $canLogActivity = $user->can('create', LeadActivity::class);
        $activityCreateUrl = route('crm.lead-activities.store', [], false);
        $canScheduleSiteVisit = $user->can('create', SiteVisit::class);
        $siteVisitStoreUrl = route('crm.site-visits.store', [], false);
        $canCreateBooking = $user->can('create', Booking::class);
        $bookingStoreUrl = route('sales.bookings.store', [], false);

        return $this->dashboardLeadQuery($user)
            ->with([
                'customer:id,name,phone',
                'project:id,name',
                'owner:id,name',
                'dispositionedBy:id,name,email',
                'latestQualification',
                'activities' => fn ($query) => $query->with(['actor:id,name', 'marketingCampaign:id,campaign_code,name'])
                    ->orderByDesc('activity_at'),
            ])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Lead $lead) => [
                'record_id' => $lead->id,
                'company_id' => $lead->company_id,
                'project_id' => $lead->project_id,
                'customer_id' => $lead->customer_id,
                'partner_id' => $lead->partner_id,
                'marketing_campaign_id' => $lead->marketing_campaign_id,
                'id' => $lead->lead_code,
                'lead_code' => $lead->lead_code,
                'name' => $lead->customer?->name ?? 'Unassigned customer',
                'phone' => $lead->customer?->phone ?? 'Phone pending',
                'source' => $lead->source,
                'project' => $lead->project?->name ?? 'Project pending',
                'budget' => $this->currency($lead->expected_value),
                'config' => 'Requirement pending',
                'status' => $lead->stage,
                'system_status' => $lead->status,
                'badge' => $this->leadBadge($lead->stage, $lead->status),
                'score' => $lead->latestQualification?->score ?? $this->leadScore($lead->stage, $lead->status),
                'latest_qualification' => $lead->latestQualification ? [
                    'id' => $lead->latestQualification->id,
                    'qualification_number' => $lead->latestQualification->qualification_number,
                    'status' => $lead->latestQualification->status,
                    'score' => $lead->latestQualification->score,
                    'budget_score' => $lead->latestQualification->budget_score,
                    'authority_score' => $lead->latestQualification->authority_score,
                    'need_score' => $lead->latestQualification->need_score,
                    'timeline_score' => $lead->latestQualification->timeline_score,
                    'quality_score' => is_array($lead->latestQualification->metadata) ? ($lead->latestQualification->metadata['quality_score'] ?? null) : null,
                    'qualified_at' => $lead->latestQualification->qualified_at?->toISOString(),
                ] : null,
                'exec' => $lead->owner?->name ?? 'Unassigned',
                'next' => $lead->follow_up_at?->diffForHumans() ?? 'No follow-up',
                'can_log_activity' => $canLogActivity,
                'activity_create_url' => $activityCreateUrl,
                'can_schedule_site_visit' => $canScheduleSiteVisit && $lead->status !== 'lost',
                'site_visit_store_url' => $siteVisitStoreUrl,
                'can_convert_booking' => $canCreateBooking && ! in_array($lead->status, ['won', 'lost'], true) && $lead->customer_id !== null,
                'booking_store_url' => $bookingStoreUrl,
                'can_disposition' => $user->can('dispose', $lead),
                'disposition_url' => '/crm/leads/'.$lead->id.'/disposition',
                'disposition' => [
                    'outcome' => $lead->disposition_outcome,
                    'reason' => $lead->disposition_reason,
                    'competitor_name' => $lead->competitor_name,
                    'note' => $lead->disposition_note,
                    'by' => $lead->dispositionedBy?->name,
                    'at' => $lead->dispositioned_at?->toISOString(),
                ],
                'activities' => $lead->activities
                    ->take(5)
                    ->map(fn ($activity) => [
                        'activity_number' => $activity->activity_number,
                        'activity_type' => $activity->activity_type,
                        'activity_at' => $activity->activity_at?->toISOString(),
                        't' => $activity->activity_at?->diffForHumans() ?? 'Time pending',
                        'who' => $activity->actor?->name ?? 'System',
                        'act' => $activity->description ?: $activity->subject,
                        'subject' => $activity->subject,
                        'outcome' => $activity->outcome,
                        'old_stage' => $activity->old_stage,
                        'new_stage' => $activity->new_stage,
                        'next_follow_up_at' => $activity->next_follow_up_at?->toISOString(),
                        'campaign' => $activity->marketingCampaign?->campaign_code,
                        'ic' => $this->leadActivityIcon($activity->activity_type),
                        'c' => $this->leadActivityColor($activity->activity_type, $activity->outcome),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function crmLeadMetrics(?User $user): ?array
    {
        if (! $user || (! $user->hasPermission('crm.view') && ! $user->hasPermission('crm.manage'))) {
            return null;
        }

        $baseQuery = $this->dashboardLeadQuery($user);
        $totalLeads = (int) (clone $baseQuery)->count();
        $openLeads = (int) (clone $baseQuery)->whereNotIn('status', ['won', 'lost'])->count();
        $newThisWeek = (int) (clone $baseQuery)->where('created_at', '>=', now()->subDays(7))->count();
        $hotLeads = (int) (clone $baseQuery)
            ->whereNotIn('status', ['won', 'lost'])
            ->where(function (Builder $query): void {
                $query->whereIn('stage', ['Negotiation', 'Site Visit Done'])
                    ->orWhereHas('qualifications', fn (Builder $qualificationQuery): Builder => $qualificationQuery->where('score', '>=', 75));
            })
            ->count();
        $followUpsDue = (int) (clone $baseQuery)
            ->whereNotIn('status', ['won', 'lost'])
            ->whereNotNull('follow_up_at')
            ->where('follow_up_at', '<=', now()->endOfDay())
            ->count();
        $overdueFollowUps = (int) (clone $baseQuery)
            ->whereNotIn('status', ['won', 'lost'])
            ->whereNotNull('follow_up_at')
            ->where('follow_up_at', '<', now())
            ->count();
        $avgResponseHours = $this->averageLeadResponseHours($user);

        return [
            'source' => 'laravel-sqlite',
            'generated_at' => now()->toISOString(),
            'summary' => [
                'total_leads' => $totalLeads,
                'open_leads' => $openLeads,
                'new_this_week' => $newThisWeek,
                'hot_leads' => $hotLeads,
                'follow_ups_due' => $followUpsDue,
                'overdue_follow_ups' => $overdueFollowUps,
                'avg_response_hours' => $avgResponseHours,
            ],
            'kanban_columns' => [
                $this->kanbanMetricColumn('New', 'b-slate', ['New'], [], $user),
                $this->kanbanMetricColumn('Contacted', 'b-blue', ['Contacted', 'Follow-up'], [], $user),
                $this->kanbanMetricColumn('Qualified', 'b-accent', ['Qualified'], [], $user),
                $this->kanbanMetricColumn('Site Visit', 'b-orange', ['Site Visit Planned', 'Site Visit Scheduled', 'Site Visit Done'], [], $user),
                $this->kanbanMetricColumn('Negotiation', 'b-violet', ['Negotiation'], [], $user),
                $this->kanbanMetricColumn('Booked', 'b-green', ['Booked'], ['won'], $user),
            ],
        ];
    }

    /**
     * @param array<int, string> $stages
     * @param array<int, string> $statuses
     * @return array<string, mixed>
     */
    private function kanbanMetricColumn(string $label, string $badge, array $stages, array $statuses, ?User $user): array
    {
        $query = $this->dashboardLeadQuery($user);

        $query->where(function (Builder $query) use ($stages, $statuses): void {
            if ($stages !== []) {
                $query->whereIn('stage', $stages);
            }

            if ($statuses !== []) {
                $method = $stages !== [] ? 'orWhereIn' : 'whereIn';
                $query->{$method}('status', $statuses);
            }
        });

        return [
            'label' => $label,
            'badge' => $badge,
            'count' => (int) $query->count(),
            'stages' => $stages,
            'statuses' => $statuses,
        ];
    }

    private function averageLeadResponseHours(?User $user): ?float
    {
        $leads = $this->dashboardLeadQuery($user)
            ->with(['activities' => fn ($query) => $query
                ->whereNotIn('activity_type', ['created'])
                ->orderBy('activity_at')])
            ->latest()
            ->limit(500)
            ->get(['id', 'company_id', 'created_at']);

        $responseHours = $leads
            ->map(function (Lead $lead): ?float {
                $firstActivityAt = $lead->activities->first()?->activity_at;

                if (! $firstActivityAt || ! $lead->created_at) {
                    return null;
                }

                return max($lead->created_at->diffInMinutes($firstActivityAt, false) / 60, 0);
            })
            ->filter(fn (?float $hours): bool => $hours !== null);

        if ($responseHours->isEmpty()) {
            return null;
        }

        return round((float) $responseHours->avg(), 1);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function crmLeadCreateOptions(?User $user): ?array
    {
        if (! $user || ! $user->hasPermission('crm.manage')) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);
        $projectIds = $this->visibleProjectIds($user);

        $companies = Company::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('id', $companyIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Company $company) => [
                'id' => $company->id,
                'code' => $company->code,
                'name' => $company->name,
            ])
            ->values()
            ->all();

        $projects = Project::query()
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('id', $projectIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'company_id', 'code', 'name'])
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'company_id' => $project->company_id,
                'code' => $project->code,
                'name' => $project->name,
            ])
            ->values()
            ->all();

        $campaigns = MarketingCampaign::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->whereIn('status', ['active', 'draft'])
            ->orderBy('campaign_code')
            ->get(['id', 'company_id', 'project_id', 'campaign_code', 'name', 'source'])
            ->map(fn (MarketingCampaign $campaign) => [
                'id' => $campaign->id,
                'company_id' => $campaign->company_id,
                'project_id' => $campaign->project_id,
                'code' => $campaign->campaign_code,
                'name' => $campaign->name,
                'source' => $campaign->source,
            ])
            ->values()
            ->all();

        return [
            'can_create' => true,
            'store_url' => '/crm/leads',
            'companies' => $companies,
            'projects' => $projects,
            'partners' => Partner::query()
                ->where('status', 'active')
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'partner_type'])
                ->map(fn (Partner $partner) => [
                    'id' => $partner->id,
                    'code' => $partner->code,
                    'name' => $partner->name,
                    'type' => $partner->partner_type,
                ])
                ->values()
                ->all(),
            'campaigns' => $campaigns,
            'sources' => collect(['Channel walk-in', 'Referral', 'Broker network', 'Walk-in', 'Google Ads', 'Facebook', 'MagicBricks', '99acres'])
                ->merge(Lead::query()->distinct()->pluck('source'))
                ->merge(collect($campaigns)->pluck('source'))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'stages' => ['New', 'Qualified', 'Site Visit Planned', 'Negotiation'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function crmQualificationOptions(?User $user): ?array
    {
        if (! $user || ! $user->can('create', LeadQualification::class)) {
            return null;
        }

        $companyId = $this->dashboardCompanyScope($user);
        $rules = app(LeadQualityScoreService::class)->rulesForCompany($companyId);

        return [
            'can_qualify' => true,
            'can_manage_scoring' => $user->can('create', ScoringRule::class),
            'index_url' => route('crm.lead-qualifications.index', [], false),
            'store_url' => route('crm.lead-qualifications.store', [], false),
            'scoring_url' => route('scoring.index', [], false),
            'rules' => [
                'rule_key' => 'lead_quality',
                'source' => $rules['source'],
                'version' => $rules['version'],
                'rule_id' => $rules['rule_id'] ?? null,
                'criteria' => $rules['criteria'],
                'bands' => $rules['bands'],
            ],
            'statuses' => [
                ['value' => 'qualified', 'label' => 'Qualified'],
                ['value' => 'nurture', 'label' => 'Nurture'],
                ['value' => 'disqualified', 'label' => 'Disqualified'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function crmImportOptions(?User $user): ?array
    {
        if (! $user || ! $user->can('create', DataImportBatch::class)) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);

        $companies = Company::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('id', $companyIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Company $company) => [
                'id' => $company->id,
                'code' => $company->code,
                'name' => $company->name,
            ])
            ->values()
            ->all();

        $headers = [
            'project_code',
            'name',
            'email',
            'phone',
            'source',
            'channel',
            'preferred_contact_method',
            'budget_min',
            'budget_max',
            'message',
            'consent_to_contact',
        ];

        return [
            'can_import' => true,
            'import_type' => DataImportBatch::TYPE_CRM_PROSPECT_INQUIRIES,
            'preview_url' => route('settings.data-imports.preview', [], false),
            'post_url_template' => '/settings/data-imports/__BATCH__/post',
            'requires_company_selection' => $user->hasPermission('*'),
            'companies' => $companies,
            'accepted_extensions' => ['.csv', '.txt'],
            'max_file_size_kb' => 512,
            'required_headers' => $headers,
            'sample_csv' => implode(',', $headers)."\n"
                .'SKY-PUN,Sample Prospect,sample.prospect@example.test,+91 99000 10001,Website,website,phone,9000000,11500000,Interested in 2BHK,yes',
            'description' => 'Imports CRM prospect inquiries with preview, row-level validation errors, duplicate warnings, reconciliation and audit trail.',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function crmSiteVisitOptions(?User $user): ?array
    {
        if (! $user || ! $user->can('create', SiteVisit::class)) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);

        $assignees = User::query()
            ->with('role:id,name,slug,permissions')
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->whereNotNull('company_id')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'company_id', 'role_id', 'name', 'email'])
            ->filter(function (User $assignee): bool {
                $permissions = $assignee->role?->permissions ?? [];

                return ! in_array('partner.portal', $permissions, true)
                    && ! in_array('buyer.view', $permissions, true);
            })
            ->map(fn (User $assignee) => [
                'id' => $assignee->id,
                'company_id' => $assignee->company_id,
                'name' => $assignee->name,
                'email' => $assignee->email,
                'role' => $assignee->role?->name,
            ])
            ->values()
            ->all();

        $leads = $this->dashboardLeadQuery($user)
            ->with(['customer:id,name,phone', 'project:id,code,name', 'owner:id,name'])
            ->whereNotIn('status', ['won', 'lost'])
            ->latest()
            ->limit(100)
            ->get(['id', 'company_id', 'project_id', 'customer_id', 'owner_user_id', 'lead_code', 'stage', 'status', 'source'])
            ->map(fn (Lead $lead) => [
                'id' => $lead->id,
                'company_id' => $lead->company_id,
                'project_id' => $lead->project_id,
                'customer_id' => $lead->customer_id,
                'lead_code' => $lead->lead_code,
                'customer_name' => $lead->customer?->name ?? 'Unassigned customer',
                'customer_phone' => $lead->customer?->phone,
                'project_name' => $lead->project?->name,
                'project_code' => $lead->project?->code,
                'owner_user_id' => $lead->owner_user_id,
                'owner_name' => $lead->owner?->name,
                'stage' => $lead->stage,
                'status' => $lead->status,
                'source' => $lead->source,
            ])
            ->values()
            ->all();

        return [
            'can_schedule' => true,
            'index_url' => route('crm.site-visits.index', [], false),
            'store_url' => route('crm.site-visits.store', [], false),
            'update_url_template' => '/crm/site-visits/__VISIT__',
            'complete_url_template' => '/crm/site-visits/__VISIT__/complete',
            'cancel_url_template' => '/crm/site-visits/__VISIT__/cancel',
            'default_duration_minutes' => 60,
            'visit_modes' => [
                ['value' => 'site', 'label' => 'Site visit'],
                ['value' => 'office', 'label' => 'Office meeting'],
                ['value' => 'virtual', 'label' => 'Virtual meeting'],
            ],
            'statuses' => ['scheduled', 'completed', 'cancelled', 'no_show'],
            'outcomes' => ['interested', 'follow_up_required', 'booking_expected', 'not_interested', 'no_show'],
            'leads' => $leads,
            'assignees' => $assignees,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function crmBookingOptions(?User $user): ?array
    {
        if (! $user || ! $user->can('create', Booking::class)) {
            return null;
        }

        return [
            'can_create' => true,
            'store_url' => route('sales.bookings.store', [], false),
            'quote_url' => route('sales.booking-quotes.store', [], false),
            'units' => $this->bookableUnitRows($user),
            'default_payment_schedule' => $this->defaultBookingPaymentSchedule(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function salesBookingOptions(?User $user): ?array
    {
        if (! $user || $this->isBuyerPortalUser($user)) {
            return null;
        }

        $canView = $user->can('viewAny', Booking::class) || $this->isPartnerPortalUser($user);
        if (! $canView) {
            return null;
        }

        $canCreate = $user->can('create', Booking::class);
        $activeStatuses = ['confirmed', 'agreement_pending', 'registered'];
        $monthStart = now()->startOfMonth()->toDateString();
        $bookingQuery = $this->dashboardBookingQuery($user);

        $bookings = (clone $bookingQuery)
            ->with(['project:id,code,name,city', 'unit:id,unit_code,tower,floor,unit_number,unit_type,total_price', 'customer:id,code,name,email,phone', 'lead:id,lead_code,stage,status', 'partner:id,code,name,partner_type', 'bookedBy:id,name,email', 'paymentSchedules'])
            ->latest('booked_on')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Booking $booking): array => $this->salesBookingBootstrapRow($booking))
            ->values()
            ->all();

        $activeQuery = (clone $bookingQuery)->whereIn('status', $activeStatuses);
        $bookingValue = (float) (clone $activeQuery)->sum('net_receivable');
        $activeBookingCount = (int) (clone $activeQuery)->count();
        $avgDealSize = $activeBookingCount > 0 ? $bookingValue / $activeBookingCount : 0.0;

        $statusCounts = (clone $bookingQuery)
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count);

        $stageDistribution = [
            ['key' => 'lead', 'label' => 'Lead', 'count' => (int) $this->dashboardLeadQuery($user)->count()],
            ['key' => 'verified', 'label' => 'Verified', 'count' => (int) $this->dashboardLeadQualificationQuery($user)->where('status', 'qualified')->count()],
            ['key' => 'site_visit', 'label' => 'Site Visit', 'count' => (int) $this->dashboardSiteVisitQuery($user)->count()],
            ['key' => 'negotiation', 'label' => 'Negotiation', 'count' => (int) $this->dashboardLeadQuery($user)->where('stage', 'Negotiation')->count()],
            ['key' => 'booking', 'label' => 'Booking', 'count' => (int) $statusCounts->only($activeStatuses)->sum()],
            ['key' => 'agreement', 'label' => 'Agreement', 'count' => (int) ($statusCounts['agreement_pending'] ?? 0)],
            ['key' => 'registration', 'label' => 'Registration', 'count' => (int) ($statusCounts['registered'] ?? 0)],
            ['key' => 'possession', 'label' => 'Possession', 'count' => (int) $this->dashboardPossessionHandoverCount($user)],
        ];

        return [
            'source' => 'laravel-sqlite',
            'generated_at' => now()->toISOString(),
            'scope' => [
                'level' => $this->dashboardScopeLevel($user),
                'company_id' => $this->dashboardCompanyScope($user),
            ],
            'index_url' => $user->can('viewAny', Booking::class) ? route('sales.bookings.index', [], false) : null,
            'store_url' => $canCreate ? route('sales.bookings.store', [], false) : null,
            'quote_url' => $canCreate ? route('sales.booking-quotes.store', [], false) : null,
            'can_view' => true,
            'can_create' => $canCreate,
            'summary' => [
                'bookings_mtd' => (int) (clone $bookingQuery)
                    ->whereIn('status', $activeStatuses)
                    ->whereDate('booked_on', '>=', $monthStart)
                    ->count(),
                'booking_value' => round($bookingValue, 2),
                'booking_value_crore' => $this->toCrore($bookingValue),
                'pending_agreements' => (int) ($statusCounts['agreement_pending'] ?? 0),
                'avg_deal_size' => round($avgDealSize, 2),
                'avg_deal_size_lakh' => round($avgDealSize / 100000, 2),
                'active_bookings' => $activeBookingCount,
                'total_bookings' => (int) (clone $bookingQuery)->count(),
            ],
            'stage_distribution' => $stageDistribution,
            'bookings' => $bookings,
            'eligible_leads' => $canCreate ? $this->eligibleBookingLeadRows($user) : [],
            'units' => $canCreate ? $this->bookableUnitRows($user) : [],
            'default_payment_schedule' => $this->defaultBookingPaymentSchedule(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bookableUnitRows(?User $user): array
    {
        $projectIds = $this->visibleProjectIds($user);

        return ProjectUnit::query()
            ->with('project:id,company_id,code,name')
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]))
            ->whereIn('status', ['available', 'reserved'])
            ->orderBy('project_id')
            ->orderBy('tower')
            ->orderBy('floor')
            ->orderBy('unit_number')
            ->limit(250)
            ->get(['id', 'company_id', 'project_id', 'unit_code', 'tower', 'floor', 'unit_number', 'unit_type', 'saleable_area_sqft', 'total_price', 'status', 'reserved_until'])
            ->filter(fn (ProjectUnit $unit): bool => $unit->isBookable())
            ->map(fn (ProjectUnit $unit): array => [
                'id' => $unit->id,
                'company_id' => $unit->company_id,
                'project_id' => $unit->project_id,
                'project_code' => $unit->project?->code,
                'project_name' => $unit->project?->name,
                'unit_code' => $unit->unit_code,
                'tower' => $unit->tower,
                'floor' => $unit->floor,
                'unit_number' => $unit->unit_number,
                'unit_type' => $unit->unit_type,
                'saleable_area_sqft' => (float) $unit->saleable_area_sqft,
                'total_price' => (float) $unit->total_price,
                'status' => $unit->status,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function eligibleBookingLeadRows(?User $user): array
    {
        return $this->dashboardLeadQuery($user)
            ->with(['project:id,code,name', 'customer:id,code,name,email,phone', 'partner:id,code,name,partner_type'])
            ->whereNotNull('customer_id')
            ->whereNotIn('status', ['won', 'lost'])
            ->whereDoesntHave('booking')
            ->latest()
            ->limit(50)
            ->get(['id', 'company_id', 'project_id', 'customer_id', 'partner_id', 'lead_code', 'source', 'stage', 'status', 'budget_min', 'budget_max', 'expected_value'])
            ->map(fn (Lead $lead): array => [
                'id' => $lead->id,
                'record_id' => $lead->id,
                'lead_code' => $lead->lead_code,
                'name' => $lead->customer?->name ?? $lead->lead_code,
                'source' => $lead->source,
                'stage' => $lead->stage,
                'status' => $lead->status,
                'company_id' => $lead->company_id,
                'project_id' => $lead->project_id,
                'project' => $lead->project?->code ?? $lead->project?->name,
                'customer_id' => $lead->customer_id,
                'customer' => $lead->customer ? [
                    'id' => $lead->customer->id,
                    'code' => $lead->customer->code,
                    'name' => $lead->customer->name,
                    'email' => $lead->customer->email,
                    'phone' => $lead->customer->phone,
                ] : null,
                'partner_id' => $lead->partner_id,
                'partner' => $lead->partner ? [
                    'id' => $lead->partner->id,
                    'code' => $lead->partner->code,
                    'name' => $lead->partner->name,
                    'type' => $lead->partner->partner_type,
                ] : null,
                'budget_min' => (float) $lead->budget_min,
                'budget_max' => (float) $lead->budget_max,
                'expected_value' => (float) $lead->expected_value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultBookingPaymentSchedule(): array
    {
        return [
            ['sequence' => 1, 'milestone' => 'Booking Amount', 'percentage' => 10],
            ['sequence' => 2, 'milestone' => 'Agreement', 'percentage' => 20],
            ['sequence' => 3, 'milestone' => 'Construction Milestone', 'percentage' => 40],
            ['sequence' => 4, 'milestone' => 'Possession', 'percentage' => 30],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function salesBookingBootstrapRow(Booking $booking): array
    {
        $netReceivable = (float) $booking->net_receivable;
        $bookingAmount = (float) $booking->booking_amount;
        $paymentPercent = $netReceivable > 0 ? round(min(100, ($bookingAmount / $netReceivable) * 100), 1) : 0.0;

        return [
            'id' => $booking->id,
            'booking_code' => $booking->booking_code,
            'status' => $booking->status,
            'status_label' => $this->bookingStatusLabel($booking->status),
            'status_badge' => $this->bookingStatusBadge($booking->status),
            'booked_on' => $booking->booked_on?->toDateString(),
            'agreement_value' => (float) $booking->agreement_value,
            'discount_amount' => (float) $booking->discount_amount,
            'tax_amount' => (float) $booking->tax_amount,
            'net_receivable' => $netReceivable,
            'net_receivable_lakh' => round($netReceivable / 100000, 2),
            'booking_amount' => $bookingAmount,
            'payment_percent' => $paymentPercent,
            'project' => $booking->project ? [
                'code' => $booking->project->code,
                'name' => $booking->project->name,
                'city' => $booking->project->city,
            ] : null,
            'unit' => $booking->unit ? [
                'unit_code' => $booking->unit->unit_code,
                'tower' => $booking->unit->tower,
                'floor' => $booking->unit->floor,
                'unit_number' => $booking->unit->unit_number,
                'unit_type' => $booking->unit->unit_type,
                'total_price' => (float) $booking->unit->total_price,
            ] : null,
            'customer' => $booking->customer ? [
                'code' => $booking->customer->code,
                'name' => $booking->customer->name,
                'email' => $booking->customer->email,
                'phone' => $booking->customer->phone,
            ] : null,
            'lead' => $booking->lead ? [
                'lead_code' => $booking->lead->lead_code,
                'stage' => $booking->lead->stage,
                'status' => $booking->lead->status,
            ] : null,
            'partner' => $booking->partner ? [
                'code' => $booking->partner->code,
                'name' => $booking->partner->name,
                'type' => $booking->partner->partner_type,
            ] : null,
            'booked_by' => $booking->bookedBy ? [
                'name' => $booking->bookedBy->name,
                'email' => $booking->bookedBy->email,
            ] : null,
            'payment_schedules' => $booking->paymentSchedules
                ->map(fn (BookingPaymentSchedule $schedule): array => [
                    'sequence' => $schedule->sequence,
                    'milestone' => $schedule->milestone,
                    'percentage' => (float) $schedule->percentage,
                    'amount' => (float) $schedule->amount,
                    'due_on' => $schedule->due_on?->toDateString(),
                    'status' => $schedule->status,
                ])
                ->values()
                ->all(),
        ];
    }

    private function bookingStatusLabel(?string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'confirmed' => 'Booking',
            'agreement_pending' => 'Agreement',
            'registered' => 'Registration',
            'cancelled' => 'Cancelled',
            default => str($status ?: 'Pending')->replace('_', ' ')->title()->toString(),
        };
    }

    private function bookingStatusBadge(?string $status): string
    {
        return match ($status) {
            'registered' => 'b-green',
            'agreement_pending' => 'b-orange',
            'confirmed' => 'b-blue',
            'cancelled' => 'b-red',
            default => 'b-slate',
        };
    }

    private function dashboardPossessionHandoverCount(?User $user): int
    {
        $bookingIds = $this->dashboardBookingQuery($user)->pluck('id');

        if ($bookingIds->isEmpty()) {
            return 0;
        }

        return (int) PossessionHandover::query()
            ->whereIn('booking_id', $bookingIds)
            ->whereIn('status', ['completed', 'handed_over'])
            ->count();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function collectionMetrics(?User $user): ?array
    {
        if (! $user || (
            ! $user->hasPermission('collections.view')
            && ! $user->hasPermission('collections.manage')
            && ! $user->hasPermission('collections.approve')
        )) {
            return null;
        }

        $today = now()->startOfDay();
        $fyStart = now()->month >= 4
            ? now()->startOfYear()->addMonths(3)->startOfDay()
            : now()->subYear()->startOfYear()->addMonths(3)->startOfDay();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $scheduleRows = $this->collectionScheduleRows($user);
        $outstandingRows = $scheduleRows->filter(fn (array $row): bool => $row['outstanding_amount'] > 0);
        $overdueRows = $outstandingRows->filter(fn (array $row): bool => $row['due_on'] !== null && $row['due_on']->lt($today));
        $dueThisMonthRows = $outstandingRows->filter(fn (array $row): bool => $row['due_on'] !== null && $row['due_on']->betweenIncluded($monthStart, $monthEnd));

        $ageingBuckets = [
            [
                'label' => 'Not due',
                'value' => $this->toCrore($outstandingRows
                    ->filter(fn (array $row): bool => $row['due_on'] === null || $row['due_on']->gte($today))
                    ->sum('outstanding_amount')),
                'color' => 'var(--green)',
            ],
            [
                'label' => '0-30d',
                'value' => $this->toCrore($overdueRows
                    ->filter(fn (array $row): bool => $row['overdue_days'] >= 1 && $row['overdue_days'] <= 30)
                    ->sum('outstanding_amount')),
                'color' => 'var(--accent)',
            ],
            [
                'label' => '31-60d',
                'value' => $this->toCrore($overdueRows
                    ->filter(fn (array $row): bool => $row['overdue_days'] >= 31 && $row['overdue_days'] <= 60)
                    ->sum('outstanding_amount')),
                'color' => 'var(--orange)',
            ],
            [
                'label' => '61-90d',
                'value' => $this->toCrore($overdueRows
                    ->filter(fn (array $row): bool => $row['overdue_days'] >= 61 && $row['overdue_days'] <= 90)
                    ->sum('outstanding_amount')),
                'color' => 'var(--red)',
            ],
            [
                'label' => '90d+',
                'value' => $this->toCrore($overdueRows
                    ->filter(fn (array $row): bool => $row['overdue_days'] > 90)
                    ->sum('outstanding_amount')),
                'color' => 'var(--red)',
            ],
        ];

        $ledgerRows = $scheduleRows
            ->sortBy([
                ['status_priority', 'asc'],
                ['due_on_sort', 'asc'],
            ])
            ->take(10)
            ->values()
            ->map(fn (array $row): array => [
                'customer' => $row['customer'],
                'unit' => $row['unit'],
                'milestone' => $row['milestone'],
                'amount' => $row['amount'],
                'paid_amount' => $row['paid_amount'],
                'outstanding_amount' => $row['outstanding_amount'],
                'amount_label' => $this->formatLakhs($row['amount']),
                'outstanding_label' => $this->formatLakhs($row['outstanding_amount']),
                'due_on' => $row['due_on']?->toDateString(),
                'status' => $row['status'],
                'badge' => $row['badge'],
            ])
            ->all();

        $collectedFy = (float) $this->dashboardCollectionQuery($user)
            ->where('status', 'approved')
            ->where('receipt_date', '>=', $fyStart->toDateString())
            ->sum('amount');

        $outstandingAmount = (float) $outstandingRows->sum('outstanding_amount');
        $overdueAmount = (float) $overdueRows->sum('outstanding_amount');
        $dueThisMonthAmount = (float) $dueThisMonthRows->sum('outstanding_amount');

        return [
            'source' => 'laravel-sqlite',
            'generated_at' => now()->toISOString(),
            'summary' => [
                'collected_fy' => round($collectedFy, 2),
                'collected_fy_crore' => $this->toCrore($collectedFy),
                'outstanding' => round($outstandingAmount, 2),
                'outstanding_crore' => $this->toCrore($outstandingAmount),
                'overdue' => round($overdueAmount, 2),
                'overdue_crore' => $this->toCrore($overdueAmount),
                'due_this_month' => round($dueThisMonthAmount, 2),
                'due_this_month_crore' => $this->toCrore($dueThisMonthAmount),
                'outstanding_units' => $outstandingRows->pluck('booking_id')->unique()->count(),
                'overdue_demands' => $overdueRows->count(),
                'due_this_month_demands' => $dueThisMonthRows->count(),
            ],
            'ageing_buckets' => $ageingBuckets,
            'ledger_rows' => $ledgerRows,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function collectionReceiptOptions(?User $user): ?array
    {
        if (! $user || ! $user->can('viewAny', CollectionReceipt::class)) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);
        $projectIds = $this->visibleProjectIds($user);

        $receipts = CollectionReceipt::query()
            ->with(['project:id,code,name', 'booking:id,booking_code,status,net_receivable', 'paymentSchedule:id,sequence,milestone,amount,due_on,status', 'customer:id,code,name,email', 'collectedBy:id,name,email', 'approvedBy:id,name,email'])
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]))
            ->latest('receipt_date')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (CollectionReceipt $receipt): array => $this->collectionReceiptBootstrapRow($receipt))
            ->values()
            ->all();

        $bookings = Booking::query()
            ->with([
                'project:id,code,name',
                'customer:id,code,name,email',
                'paymentSchedules.collectionReceipts' => fn ($query) => $query->whereIn('status', ['submitted', 'approved']),
                'paymentSchedules.paymentRequests' => fn ($query) => $query->where('status', 'requested'),
                'collectionReceipts' => fn ($query) => $query->whereIn('status', ['submitted', 'approved']),
            ])
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]))
            ->whereIn('status', ['confirmed', 'agreement_pending', 'registered'])
            ->latest('booked_on')
            ->limit(25)
            ->get()
            ->map(fn (Booking $booking): array => $this->paymentRequestBookingBootstrapRow($booking))
            ->filter(fn (array $booking): bool => $booking['outstanding_amount'] > 0)
            ->values()
            ->all();

        return [
            'source' => 'laravel-sqlite',
            'index_url' => route('finance.collections.index', [], false),
            'export_url' => route('finance.collections.export', [], false),
            'store_url' => route('finance.collections.store', [], false),
            'approve_url_template' => '/finance/collections/__RECEIPT__/approve',
            'can_export' => $user->can('viewAny', CollectionReceipt::class),
            'can_create' => $user->can('create', CollectionReceipt::class),
            'can_approve' => $user->hasPermission('collections.approve'),
            'payment_modes' => ['cash', 'cheque', 'neft', 'rtgs', 'upi', 'online'],
            'receipts' => $receipts,
            'bookings' => $bookings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectionReceiptBootstrapRow(CollectionReceipt $receipt): array
    {
        return [
            'id' => $receipt->id,
            'receipt_number' => $receipt->receipt_number,
            'status' => $receipt->status,
            'receipt_date' => $receipt->receipt_date?->toDateString(),
            'payment_mode' => $receipt->payment_mode,
            'instrument_number' => $receipt->instrument_number,
            'bank_name' => $receipt->bank_name,
            'amount' => (float) $receipt->amount,
            'tax_deducted_amount' => (float) $receipt->tax_deducted_amount,
            'notes' => $receipt->notes,
            'project' => $receipt->project ? [
                'code' => $receipt->project->code,
                'name' => $receipt->project->name,
            ] : null,
            'booking' => $receipt->booking ? [
                'booking_code' => $receipt->booking->booking_code,
                'status' => $receipt->booking->status,
                'net_receivable' => (float) $receipt->booking->net_receivable,
            ] : null,
            'payment_schedule' => $receipt->paymentSchedule ? [
                'sequence' => (int) $receipt->paymentSchedule->sequence,
                'milestone' => $receipt->paymentSchedule->milestone,
                'amount' => (float) $receipt->paymentSchedule->amount,
                'due_on' => $receipt->paymentSchedule->due_on?->toDateString(),
                'status' => $receipt->paymentSchedule->status,
            ] : null,
            'customer' => $receipt->customer ? [
                'code' => $receipt->customer->code,
                'name' => $receipt->customer->name,
                'email' => $receipt->customer->email,
            ] : null,
            'collected_by' => $receipt->collectedBy ? [
                'name' => $receipt->collectedBy->name,
                'email' => $receipt->collectedBy->email,
            ] : null,
            'approved_by' => $receipt->approvedBy ? [
                'name' => $receipt->approvedBy->name,
                'email' => $receipt->approvedBy->email,
            ] : null,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function collectionScheduleRows(?User $user)
    {
        $today = now()->startOfDay();

        return BookingPaymentSchedule::query()
            ->with([
                'booking:id,company_id,project_id,project_unit_id,customer_id,booking_code,status',
                'booking.customer:id,name',
                'booking.unit:id,unit_code',
                'collectionReceipts' => fn ($query) => $query->where('status', 'approved'),
            ])
            ->whereHas('booking', function (Builder $query) use ($user): void {
                $this->constrainDashboardBookingQuery($query, $user)
                    ->whereIn('status', ['confirmed', 'agreement_pending', 'registered']);
            })
            ->orderBy('due_on')
            ->orderBy('sequence')
            ->get()
            ->map(function (BookingPaymentSchedule $schedule) use ($today): array {
                $amount = (float) $schedule->amount;
                $paidAmount = (float) $schedule->collectionReceipts->sum('amount');
                $outstanding = max(round($amount - $paidAmount, 2), 0);
                $dueOn = $schedule->due_on?->startOfDay();
                $overdueDays = $dueOn && $dueOn->lt($today) ? $dueOn->diffInDays($today) : 0;

                if ($outstanding <= 0) {
                    $status = 'Paid';
                    $badge = 'b-green';
                    $priority = 4;
                } elseif ($paidAmount > 0) {
                    $status = 'Partially Paid';
                    $badge = 'b-orange';
                    $priority = 1;
                } elseif ($dueOn && $dueOn->lt($today)) {
                    $status = 'Overdue';
                    $badge = 'b-red';
                    $priority = 0;
                } else {
                    $status = 'Due';
                    $badge = 'b-blue';
                    $priority = 2;
                }

                return [
                    'booking_id' => $schedule->booking_id,
                    'customer' => $schedule->booking?->customer?->name ?? 'Customer pending',
                    'unit' => $schedule->booking?->unit?->unit_code ?? 'Unit pending',
                    'milestone' => $schedule->milestone,
                    'amount' => $amount,
                    'paid_amount' => round($paidAmount, 2),
                    'outstanding_amount' => $outstanding,
                    'due_on' => $dueOn,
                    'due_on_sort' => $dueOn?->toDateString() ?? '9999-12-31',
                    'overdue_days' => $overdueDays,
                    'status' => $status,
                    'badge' => $badge,
                    'status_priority' => $priority,
                ];
            });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function marketingMetrics(?User $user): ?array
    {
        if (! $user || (! $user->hasPermission('crm.view') && ! $user->hasPermission('crm.manage'))) {
            return null;
        }

        $fyStart = now()->month >= 4
            ? now()->startOfYear()->addMonths(3)->startOfDay()
            : now()->subYear()->startOfYear()->addMonths(3)->startOfDay();

        $campaigns = $this->dashboardCampaignQuery($user)
            ->with('project:id,code,name')
            ->orderByDesc('start_on')
            ->orderByDesc('id')
            ->get();

        $rows = $campaigns->map(function (MarketingCampaign $campaign): array {
            $leadIds = Lead::query()
                ->where('marketing_campaign_id', $campaign->id)
                ->pluck('id');
            $leadCount = $leadIds->count();
            $verifiedCount = $leadCount > 0
                ? LeadQualification::query()->whereIn('lead_id', $leadIds)->where('status', 'qualified')->count()
                : 0;
            $visitCount = $leadCount > 0
                ? SiteVisit::query()->whereIn('lead_id', $leadIds)->count()
                : 0;
            $bookingsQuery = $leadCount > 0
                ? Booking::query()->whereIn('lead_id', $leadIds)
                : Booking::query()->whereKey([]);
            $bookingCount = (clone $bookingsQuery)->count();
            $revenue = (float) (clone $bookingsQuery)->sum('net_receivable');
            $spend = (float) $campaign->budget_amount;

            return [
                'campaign_code' => $campaign->campaign_code,
                'name' => $campaign->name,
                'channel' => $campaign->channel,
                'source' => $campaign->source,
                'project' => $campaign->project?->code,
                'status' => $campaign->status,
                'spend' => round($spend, 2),
                'spend_lakh' => round($spend / 100000, 2),
                'leads' => $leadCount,
                'verified' => $verifiedCount,
                'visits' => $visitCount,
                'bookings' => $bookingCount,
                'revenue' => round($revenue, 2),
                'roi' => $spend > 0 ? round(($revenue / $spend) * 100, 1) : null,
            ];
        })->values();

        $totalSpend = (float) $this->dashboardCampaignQuery($user)
            ->where('start_on', '>=', $fyStart->toDateString())
            ->sum('budget_amount');
        $totalLeads = (int) $rows->sum('leads');
        $totalBookings = (int) $rows->sum('bookings');
        $totalRevenue = (float) $rows->sum('revenue');

        return [
            'source' => 'laravel-sqlite',
            'generated_at' => now()->toISOString(),
            'index_url' => route('crm.campaigns.index', [], false),
            'store_url' => $user->can('create', MarketingCampaign::class) ? route('crm.campaigns.store', [], false) : null,
            'status_url_template' => '/crm/campaigns/__CAMPAIGN__/status',
            'can_create' => $user->can('create', MarketingCampaign::class),
            'can_update_status' => $user->hasPermission('crm.manage'),
            'channels' => [
                ['value' => 'digital', 'label' => 'Digital'],
                ['value' => 'print', 'label' => 'Print'],
                ['value' => 'outdoor', 'label' => 'Outdoor'],
                ['value' => 'referral', 'label' => 'Referral'],
                ['value' => 'channel_partner', 'label' => 'Channel Partner'],
                ['value' => 'event', 'label' => 'Event'],
                ['value' => 'portal', 'label' => 'Portal'],
                ['value' => 'social', 'label' => 'Social'],
                ['value' => 'email', 'label' => 'Email'],
                ['value' => 'sms', 'label' => 'SMS'],
                ['value' => 'other', 'label' => 'Other'],
            ],
            'statuses' => [
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'paused', 'label' => 'Paused'],
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'archived', 'label' => 'Archived'],
            ],
            'companies' => $this->marketingCompanyRows($user),
            'projects' => $this->marketingProjectRows($user),
            'summary' => [
                'marketing_spend' => round($totalSpend, 2),
                'marketing_spend_crore' => $this->toCrore($totalSpend),
                'cost_per_lead' => $totalLeads > 0 ? round($totalSpend / $totalLeads, 2) : null,
                'cost_per_booking' => $totalBookings > 0 ? round($totalSpend / $totalBookings, 2) : null,
                'cost_per_booking_lakh' => $totalBookings > 0 ? round(($totalSpend / $totalBookings) / 100000, 2) : null,
                'blended_roi' => $totalSpend > 0 ? round(($totalRevenue / $totalSpend) * 100, 1) : null,
                'campaign_count' => $campaigns->count(),
                'leads' => $totalLeads,
                'bookings' => $totalBookings,
            ],
            'campaigns' => $rows->take(20)->values()->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function marketingCompanyRows(?User $user): array
    {
        $companyIds = $this->visibleCompanyIds($user);

        return Company::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('id', $companyIds ?: [0]))
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'status'])
            ->map(fn (Company $company): array => [
                'id' => $company->id,
                'code' => $company->code,
                'name' => $company->name,
                'label' => $company->code.' · '.$company->name,
                'status' => $company->status,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function marketingProjectRows(?User $user): array
    {
        $companyIds = $this->visibleCompanyIds($user);
        $projectIds = $this->visibleProjectIds($user);

        return Project::query()
            ->with('company:id,code,name')
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('id', $projectIds ?: [0]))
            ->orderBy('code')
            ->get(['id', 'company_id', 'code', 'name', 'city', 'status'])
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'company_id' => $project->company_id,
                'company_code' => $project->company?->code,
                'code' => $project->code,
                'name' => $project->name,
                'city' => $project->city,
                'label' => $project->code.' · '.$project->name,
                'status' => $project->status,
            ])
            ->values()
            ->all();
    }

    /**
     * @return Builder<MarketingCampaign>
     */
    private function dashboardCampaignQuery(?User $user): Builder
    {
        return MarketingCampaign::query()
            ->when($this->isPartnerPortalUser($user) || $this->isBuyerPortalUser($user), fn (Builder $query) => $query->whereKey([]))
            ->when(! $this->isExternalDashboardUser($user), fn (Builder $query) => $this->scope($query, $this->dashboardCompanyScope($user)));
    }

    /**
     * @return array<int, array{group: string, items: mixed}>
     */
    private function moduleGroups(?User $user, ?User $actor = null): array
    {
        $approvedRoutes = $this->approvedShellRoutes();
        $actor ??= $user;

        return ErpModule::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['slug', 'name', 'group_name', 'route', 'icon', 'required_permissions'])
            ->filter(fn (ErpModule $module): bool => in_array($module->route ?: $module->slug, $approvedRoutes, true))
            ->filter(fn (ErpModule $module): bool => $this->canSeeModule($user, $module))
            ->filter(fn (ErpModule $module): bool => $this->canSeeModule($actor, $module))
            ->groupBy('group_name')
            ->map(fn ($modules, string $group) => [
                'group' => $group,
                'items' => $modules->map(fn (ErpModule $module) => [
                    'slug' => $module->slug,
                    'name' => $module->name,
                    'route' => $module->route,
                    'icon' => $module->icon,
                    'required_permissions' => $module->required_permissions ?? $this->defaultModulePermissions($module->slug),
                ])->values(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function approvedShellRoutes(): array
    {
        return [
            'dashboard',
            'approvals',
            'notifications',
            'reports',
            'tasks',
            'calendar',
            'chat',
            'mailbox',
            'leads',
            'qualification',
            'sitevisits',
            'sales',
            'marketing',
            'collections',
            'funnel',
            'performance',
            'projects',
            'inventory',
            'pricing',
            'cost',
            'planning',
            'progress',
            'materials',
            'procurement',
            'vendors',
            'contractors',
            'boq',
            'hr',
            'ess',
            'payroll',
            'recruitment',
            'finance',
            'legal',
            'documents',
            'possession',
            'complaints',
            'maintenance',
            'buyer',
            'inquiry',
            'admin',
            'workflows',
            'audit',
            'settings',
            'scoring',
            'partner',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function companyRows(?User $user): array
    {
        $companyIds = $this->visibleCompanyIds($user);

        return Company::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('id', $companyIds ?: [0]))
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'state', 'status'])
            ->map(fn (Company $company) => [
                'id' => $company->id,
                'code' => $company->code,
                'name' => $company->name,
                'state' => $company->state,
                'status' => $company->status,
                'counts' => $this->companyCounts($company, $user),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function projectRows(?User $user): array
    {
        $projectIds = $this->visibleProjectIds($user);

        return Project::query()
            ->with('company:id,code,name')
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('id', $projectIds ?: [0]))
            ->orderBy('code')
            ->get(['id', 'company_id', 'code', 'name', 'city', 'state', 'status', 'budget_amount', 'target_roi_percent'])
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'code' => $project->code,
                'name' => $project->name,
                'company_id' => $project->company_id,
                'company' => $project->company?->code,
                'city' => $project->city,
                'state' => $project->state,
                'status' => $project->status,
                'budget_amount' => $this->isExternalDashboardUser($user) ? 0.0 : (float) $project->budget_amount,
                'target_roi_percent' => $this->isExternalDashboardUser($user) ? 0.0 : (float) $project->target_roi_percent,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function projectMasterOptions(?User $user): ?array
    {
        if (! $user || ! $user->can('viewAny', Project::class) || $this->isPartnerPortalUser($user) || $this->isBuyerPortalUser($user)) {
            return null;
        }

        $companyIds = $this->visibleCompanyIds($user);

        $companies = Company::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('id', $companyIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'state'])
            ->map(fn (Company $company): array => [
                'id' => $company->id,
                'code' => $company->code,
                'name' => $company->name,
                'state' => $company->state,
                'label' => $company->code.' · '.$company->name,
            ])
            ->values()
            ->all();

        $branches = Branch::query()
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'company_id', 'code', 'name', 'city', 'state'])
            ->map(fn (Branch $branch): array => [
                'id' => $branch->id,
                'company_id' => $branch->company_id,
                'code' => $branch->code,
                'name' => $branch->name,
                'city' => $branch->city,
                'state' => $branch->state,
                'label' => $branch->code.' · '.$branch->name,
            ])
            ->values()
            ->all();

        $assignableUsers = User::query()
            ->with(['role:id,name', 'employee:id,user_id,employee_code,department,designation,status'])
            ->when(is_array($companyIds), fn (Builder $query) => $query->whereIn('company_id', $companyIds ?: [0]))
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(250)
            ->get(['id', 'company_id', 'role_id', 'name', 'email'])
            ->map(fn (User $candidate): array => [
                'id' => $candidate->id,
                'company_id' => $candidate->company_id,
                'name' => $candidate->name,
                'email' => $candidate->email,
                'role' => $candidate->role?->name,
                'employee_id' => $candidate->employee?->id,
                'employee_code' => $candidate->employee?->employee_code,
                'department' => $candidate->employee?->department,
                'designation' => $candidate->employee?->designation,
                'label' => trim($candidate->name.' · '.($candidate->role?->name ?? 'User')),
            ])
            ->values()
            ->all();

        return [
            'source' => 'laravel-sqlite',
            'store_url' => route('projects.store', [], false),
            'update_url_template' => '/projects/__PROJECT__',
            'team_assignment_store_url_template' => '/projects/__PROJECT__/team-assignments',
            'team_assignment_revoke_url_template' => '/projects/__PROJECT__/team-assignments/__ASSIGNMENT__',
            'can_create_project' => $user->can('create', Project::class),
            'can_update_project' => $user->can('create', Project::class),
            'can_manage_project_team' => $user->can('create', ProjectTeamAssignment::class),
            'companies' => $companies,
            'branches' => $branches,
            'assignable_users' => $assignableUsers,
            'team_access_levels' => [
                ['value' => 'read', 'label' => 'Read'],
                ['value' => 'contribute', 'label' => 'Contribute'],
                ['value' => 'manage', 'label' => 'Manage'],
                ['value' => 'approve', 'label' => 'Approve'],
            ],
            'project_types' => [
                ['value' => 'residential', 'label' => 'Residential'],
                ['value' => 'commercial', 'label' => 'Commercial'],
                ['value' => 'villa', 'label' => 'Villa'],
                ['value' => 'mixed_use', 'label' => 'Mixed Use'],
                ['value' => 'plotted', 'label' => 'Plotted'],
                ['value' => 'redevelopment', 'label' => 'Redevelopment'],
            ],
            'statuses' => [
                ['value' => 'planned', 'label' => 'Planned'],
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'on_hold', 'label' => 'On Hold'],
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'archived', 'label' => 'Archived'],
            ],
        ];
    }

    /**
     * @return array{partners: array<int, array<string, mixed>>, lead_value_by_stage: array<int, array<string, mixed>>}
     */
    private function partnerPipeline(?User $user): array
    {
        if ($this->isBuyerPortalUser($user)) {
            return ['partners' => [], 'lead_value_by_stage' => []];
        }

        $partnerIds = $this->isPartnerPortalUser($user) ? $this->partnerIdsForUser($user) : [];
        $canSeeAllPartners = $user?->hasPermission('*') === true || $user?->hasPermission('crm.view') === true || $user?->hasPermission('reports.view') === true;

        if ($this->isPartnerPortalUser($user) && $partnerIds === []) {
            return ['partners' => [], 'lead_value_by_stage' => []];
        }

        if (! $canSeeAllPartners && $partnerIds === []) {
            return ['partners' => [], 'lead_value_by_stage' => []];
        }

        $partners = Partner::query()
            ->withCount('leads')
            ->when($partnerIds !== [], fn (Builder $query) => $query->whereIn('id', $partnerIds))
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'partner_type', 'status'])
            ->map(fn (Partner $partner) => [
                'code' => $partner->code,
                'name' => $partner->name,
                'type' => $partner->partner_type,
                'status' => $partner->status,
                'leads' => $partner->leads_count,
            ])
            ->values()
            ->all();

        $leadValueByStage = Lead::query()
            ->when($partnerIds !== [], fn (Builder $query) => $query->whereIn('partner_id', $partnerIds))
            ->select('stage', DB::raw('count(*) as lead_count'), DB::raw('sum(expected_value) as pipeline_value'))
            ->groupBy('stage')
            ->orderBy('stage')
            ->get()
            ->map(fn ($row) => [
                'stage' => $row->stage,
                'lead_count' => (int) $row->lead_count,
                'pipeline_value' => (float) $row->pipeline_value,
            ])
            ->values()
            ->all();

        return ['partners' => $partners, 'lead_value_by_stage' => $leadValueByStage];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function partnerPortal(?User $user): ?array
    {
        if (! $this->isPartnerPortalUser($user)) {
            return null;
        }

        return app(PartnerDashboardService::class)->summaryFor($user, 10);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buyerPortal(?User $user): ?array
    {
        if (! $this->isBuyerPortalUser($user)) {
            return null;
        }

        $summary = app(BuyerPortalSummaryService::class)->summaryFor($user);

        $summary['endpoints'] = [
            'summary_url' => route('buyer.summary', [], false),
            'bookings_url' => route('buyer.bookings.index', [], false),
            'receipts_url' => route('buyer.receipts.index', [], false),
            'payment_requests_url' => route('buyer.payment-requests.index', [], false),
            'payment_request_pay_url_template' => '/buyer/payment-requests/__PAYMENT_REQUEST__/pay',
            'documents_url' => route('buyer.documents.index', [], false),
            'service_tickets_url' => route('buyer.service-tickets.index', [], false),
            'service_tickets_store_url' => route('buyer.service-tickets.store', [], false),
            'service_ticket_close_url_template' => '/buyer/service-tickets/__SERVICE_TICKET__/close',
        ];

        return $summary;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mobileJourneyOptions(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $isBuyer = $this->isBuyerPortalUser($user);
        $isPartner = $this->isPartnerPortalUser($user);
        $canUseEmployeeSelfService = $user->hasPermission('employee.self_service');
        $canUseConstruction = ! $isPartner && ! $isBuyer && ($user->hasPermission('*') || $user->hasPermission('construction.view') || $user->hasPermission('construction.manage'));
        $canUseSales = ! $isBuyer && ($user->hasPermission('*') || $user->hasPermission('crm.view') || $user->hasPermission('crm.manage') || $user->hasPermission('booking.view') || $user->hasPermission('booking.manage') || $user->hasPermission('partner.portal'));
        $canUseApprovals = ! $isPartner && ! $isBuyer && ($user->hasPermission('*') || $user->hasPermission('settings.approve') || $user->hasPermission('finance.approve') || $user->hasPermission('hr.manage') || $user->hasPermission('booking.manage'));

        $taskQuery = WorkTask::query()
            ->where('assigned_to_user_id', $user->id)
            ->whereNotIn('status', ['completed', 'cancelled']);

        $staffTasks = $canUseEmployeeSelfService || $canUseConstruction || $canUseSales
            ? (int) $taskQuery->count()
            : 0;

        $pendingApprovals = $canUseApprovals ? count($this->approvalRows($user)) : 0;
        $openBuyerTickets = $isBuyer
            ? (int) ServiceTicket::query()
                ->where('customer_id', $user->customer?->id ?? 0)
                ->whereIn('status', ['open', 'assigned', 'in_progress', 'escalated'])
                ->count()
            : 0;

        return [
            'source' => 'laravel-sqlite',
            'user' => [
                'name' => $user->name,
                'role' => $user->role?->name,
                'scope' => $this->dashboardScopeLevel($user),
            ],
            'auth' => [
                'login_route' => route('login', [], false),
                'native_app_auth_status' => 'not_implemented',
                'message' => 'Builder360 provides responsive web journeys. Separate native mobile apps, OTP device binding and app-store deployment are not included.',
            ],
            'staff' => [
                'available' => $canUseEmployeeSelfService || $canUseConstruction || $canUseSales,
                'open_tasks' => $staffTasks,
                'pending_approvals' => $pendingApprovals,
                'employee_self_service' => $canUseEmployeeSelfService,
                'construction_journey' => $canUseConstruction,
                'sales_journey' => $canUseSales,
            ],
            'buyer' => [
                'available' => $isBuyer,
                'booking_count' => $isBuyer ? (int) Booking::query()->where('customer_id', $user->customer?->id ?? 0)->count() : 0,
                'open_tickets' => $openBuyerTickets,
                'summary_available' => $isBuyer,
            ],
            'capabilities' => [
                [
                    'name' => 'Employee self-service',
                    'status' => $canUseEmployeeSelfService ? 'available' : 'not_available',
                    'detail' => $canUseEmployeeSelfService ? 'Uses employee self-service, attendance, leave, tasks and helpdesk records.' : 'Current role does not have employee self-service access.',
                ],
                [
                    'name' => 'Site/construction mobile journey',
                    'status' => $canUseConstruction ? 'available' : 'not_available',
                    'detail' => $canUseConstruction ? 'Uses construction, daily progress, procurement and task records.' : 'Current role does not have construction mobile access.',
                ],
                [
                    'name' => 'Sales mobile journey',
                    'status' => $canUseSales ? 'available' : 'not_available',
                    'detail' => $canUseSales ? 'Uses CRM leads, site visits, bookings and partner data where applicable.' : 'Current role does not have sales or partner mobile access.',
                ],
                [
                    'name' => 'Buyer mobile journey',
                    'status' => $isBuyer ? 'available' : 'not_available',
                    'detail' => $isBuyer ? 'Uses buyer portal booking, payment, document and service ticket records.' : 'Buyer mobile data is only available to authenticated buyer users.',
                ],
                [
                    'name' => 'Native app package',
                    'status' => 'not_implemented',
                    'detail' => 'Android and iOS mobile applications are not included in the current delivery.',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function notifications(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return array_merge(app(NotificationSummaryService::class)->summaryFor($user), [
            'endpoints' => [
                'index_url' => route('notifications.index', [], false),
                'summary_url' => route('notifications.summary', [], false),
                'read_all_url' => route('notifications.read-all', [], false),
                'read_url_template' => '/notifications/__NOTIFICATION__/read',
                'archive_url_template' => '/notifications/__NOTIFICATION__/archive',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardForUser(?User $user): array
    {
        $companyId = $this->dashboardCompanyScope($user);

        $projectRows = $this->dashboardProjects($user);
        $unitCounts = $this->unitCounts($user);
        $salesValue = (float) $this->dashboardBookingQuery($user)->whereIn('status', ['confirmed', 'agreement_pending', 'registered'])->sum('net_receivable');
        $collections = (float) $this->dashboardCollectionQuery($user)->where('status', 'approved')->sum('amount');
        $leadCount = (int) $this->dashboardLeadQuery($user)->count();
        $qualifiedCount = (int) $this->dashboardLeadQualificationQuery($user)->where('status', 'qualified')->count();
        $siteVisitCount = (int) $this->dashboardSiteVisitQuery($user)->count();
        $bookingCount = (int) $this->dashboardBookingQuery($user)->whereIn('status', ['confirmed', 'agreement_pending', 'registered'])->count();
        $pendingApprovals = $this->approvalRows($user);

        $roiValues = collect($projectRows)->pluck('roi')->filter(fn ($roi): bool => $roi > 0);

        return [
            'source' => 'laravel-sqlite',
            'generated_at' => now()->toISOString(),
            'scope' => [
                'company_id' => $companyId,
                'company_code' => $user?->company?->code,
                'level' => $this->dashboardScopeLevel($user),
                'selected_project_id' => $this->selectedProjectId,
            ],
            'kpis' => [
                'projects' => count($projectRows),
                'activeSites' => collect($projectRows)->where('progress', '>', 0)->count(),
                'totalUnits' => $unitCounts['total'],
                'available' => $unitCounts['available'],
                'hold' => $unitCounts['hold'],
                'booked' => $unitCounts['booked'],
                'sold' => $unitCounts['sold'],
                'soldOnly' => $unitCounts['booked'] + $unitCounts['sold'],
                'leads' => $leadCount,
                'verified' => $qualifiedCount,
                'siteVisits' => $siteVisitCount,
                'bookings' => $bookingCount,
                'collection' => $this->toCrore($collections),
                'outstanding' => $this->toCrore(max($salesValue - $collections, 0)),
                'expenses' => $this->toCrore($this->approvedSpend($user)),
                'budgetVar' => $this->budgetVariance($projectRows),
                'roi' => round($roiValues->avg() ?? 0, 1),
                'pendingApprovals' => count($pendingApprovals),
            ],
            'projects' => $projectRows,
            'funnel' => $this->funnelRows($user),
            'approvals' => $pendingApprovals,
            'alerts' => $this->alertRows($user),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dashboardProjects(?User $user): array
    {
        $projects = $this->dashboardProjectQuery($user)
            ->with([
                'units',
                'bookings' => fn ($query) => $this->constrainDashboardBookingQuery($query, $user),
                'collectionReceipts' => fn ($query) => $this->constrainDashboardCollectionQuery($query, $user),
                'boqItems',
                'teamAssignments' => fn ($query) => $query
                    ->with(['user:id,name,email', 'employee:id,user_id,employee_code,department,designation'])
                    ->where('status', 'active')
                    ->orderBy('role_label')
                    ->orderBy('id'),
            ])
            ->orderBy('code')
            ->get();
        $companyId = $this->dashboardCompanyScope($user);
        $healthScores = $companyId === null ? [] : $this->readCurrentScores->execute(
            $companyId,
            'project_health',
            Project::class,
            $projects->modelKeys(),
        );

        return $projects->map(function (Project $project) use ($user, $healthScores): array {
                $units = $project->units;
                $bookings = $project->bookings->whereIn('status', ['confirmed', 'agreement_pending', 'registered']);
                $collections = $project->collectionReceipts->where('status', 'approved');
                $budget = $this->isExternalDashboardUser($user) ? 0.0 : (float) $project->budget_amount;
                $revenue = (float) $bookings->sum('net_receivable');
                $collected = (float) $collections->sum('amount');
                $spent = $this->isExternalDashboardUser($user) ? 0 : (float) ContractorMeasurement::query()
                    ->where('project_id', $project->id)
                    ->where('status', 'approved')
                    ->sum('certified_total');
                $spent += $this->isExternalDashboardUser($user) ? 0 : (float) PurchaseOrder::query()
                    ->where('project_id', $project->id)
                    ->whereIn('status', ['approved', 'partially_received', 'received'])
                    ->sum('total_amount');
                $progress = (float) ConstructionMilestone::query()
                    ->where('project_id', $project->id)
                    ->avg('progress_percent');
                $sold = $units->whereIn('status', ['booked', 'registered', 'handed_over'])->count();
                $saleableArea = (float) $units->sum('saleable_area_sqft');
                $collectionPercent = $revenue > 0 ? $collected / $revenue * 100 : 0;
                $healthScore = $healthScores[$project->id] ?? null;
                $unitStatusCounts = $units
                    ->groupBy('status')
                    ->map(fn ($items): int => $items->count())
                    ->all();

                return [
                    'db_id' => $project->id,
                    'id' => strtolower(str_replace('-', '_', $project->code)),
                    'company_id' => $project->company_id,
                    'branch_id' => $project->branch_id,
                    'name' => $project->name,
                    'code' => $project->code,
                    'city' => $project->city,
                    'type' => $project->project_type,
                    'project_type' => $project->project_type,
                    'state' => $project->state,
                    'color' => $this->projectColor($project->code),
                    'status' => $project->status,
                    'rera' => 'Available',
                    'units' => $units->count(),
                    'sold' => $sold,
                    'progress' => round($progress ?: 0),
                    'budget' => $this->toCrore($budget),
                    'spent' => $this->toCrore($spent),
                    'revenue' => $this->toCrore($revenue),
                    'collected' => $this->toCrore($collected),
                    'outstanding' => $this->toCrore(max($revenue - $collected, 0)),
                    'revenue_amount' => round($revenue, 2),
                    'collected_amount' => round($collected, 2),
                    'outstanding_amount' => round(max($revenue - $collected, 0), 2),
                    'collection_percent' => round($collectionPercent, 1),
                    'budget_amount' => $budget,
                    'saleable_area_sqft' => round($saleableArea, 2),
                    'roi' => $this->isExternalDashboardUser($user) ? 0.0 : (float) $project->target_roi_percent,
                    'target_roi_percent' => $this->isExternalDashboardUser($user) ? 0.0 : (float) $project->target_roi_percent,
                    'starts_on' => $project->starts_on?->toDateString(),
                    'ends_on' => $project->ends_on?->toDateString(),
                    'health' => $healthScore ? (float) str_replace(',', '', $healthScore->score) : null,
                    'health_band' => $healthScore?->band,
                    'health_rule_version' => $healthScore?->ruleVersion,
                    'health_calculated_at' => $healthScore?->calculatedAt->toISOString(),
                    'unit_status_counts' => [
                        'available' => (int) ($unitStatusCounts['available'] ?? 0),
                        'reserved' => (int) ($unitStatusCounts['reserved'] ?? 0),
                        'booked' => (int) ($unitStatusCounts['booked'] ?? 0),
                        'registered' => (int) ($unitStatusCounts['registered'] ?? 0),
                        'handed_over' => (int) ($unitStatusCounts['handed_over'] ?? 0),
                        'blocked' => (int) ($unitStatusCounts['blocked'] ?? 0),
                        'on_hold' => (int) ($unitStatusCounts['on_hold'] ?? 0),
                    ],
                    'tower_rows' => $this->projectTowerRows($units),
                    'unit_rows' => $this->projectUnitRows($units),
                    'team_rows' => $this->projectTeamRows($project->teamAssignments),
                    'mgr' => 'Available',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param mixed $units
     * @return array<int, array<string, mixed>>
     */
    private function projectTowerRows($units): array
    {
        return $units
            ->groupBy(fn (ProjectUnit $unit): string => trim((string) $unit->tower) !== '' ? (string) $unit->tower : 'Unassigned')
            ->sortKeys()
            ->map(function ($towerUnits, string $tower): array {
                $statusCounts = $towerUnits
                    ->groupBy('status')
                    ->map(fn ($items): int => $items->count());

                return [
                    'tower' => $tower,
                    'floors' => $towerUnits->pluck('floor')->filter(fn ($floor): bool => $floor !== null)->unique()->count(),
                    'units' => $towerUnits->count(),
                    'available' => (int) ($statusCounts['available'] ?? 0),
                    'reserved' => (int) ($statusCounts['reserved'] ?? 0),
                    'booked' => (int) ($statusCounts['booked'] ?? 0),
                    'registered' => (int) ($statusCounts['registered'] ?? 0),
                    'handed_over' => (int) ($statusCounts['handed_over'] ?? 0),
                    'blocked' => (int) ($statusCounts['blocked'] ?? 0),
                    'sold' => (int) (($statusCounts['booked'] ?? 0) + ($statusCounts['registered'] ?? 0) + ($statusCounts['handed_over'] ?? 0)),
                    'inventory_value' => round((float) $towerUnits->sum('total_price'), 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param mixed $units
     * @return array<int, array<string, mixed>>
     */
    private function projectUnitRows($units): array
    {
        return $units
            ->sortBy([
                ['tower', 'asc'],
                ['floor', 'desc'],
                ['unit_number', 'asc'],
            ])
            ->take(24)
            ->map(fn (ProjectUnit $unit): array => [
                'id' => $unit->id,
                'unit_code' => $unit->unit_code,
                'tower' => $unit->tower,
                'floor' => $unit->floor,
                'unit_number' => $unit->unit_number,
                'unit_type' => $unit->unit_type,
                'saleable_area_sqft' => (float) $unit->saleable_area_sqft,
                'total_price' => (float) $unit->total_price,
                'status' => $unit->status,
            ])
            ->values()
            ->all();
    }

    /**
     * @param mixed $assignments
     * @return array<int, array<string, mixed>>
     */
    private function projectTeamRows($assignments): array
    {
        return $assignments
            ->map(fn (ProjectTeamAssignment $assignment): array => [
                'id' => $assignment->id,
                'user_id' => $assignment->user_id,
                'employee_id' => $assignment->employee_id,
                'name' => $assignment->user?->name ?? 'Assigned User',
                'email' => $assignment->user?->email,
                'employee_code' => $assignment->employee?->employee_code,
                'role' => $assignment->role_label,
                'role_label' => $assignment->role_label,
                'dept' => $assignment->department ?: $assignment->employee?->department,
                'department' => $assignment->department ?: $assignment->employee?->department,
                'designation' => $assignment->employee?->designation,
                'access' => $assignment->access_level,
                'access_level' => $assignment->access_level,
                'status' => $assignment->status,
                'starts_on' => $assignment->starts_on?->toDateString(),
                'ends_on' => $assignment->ends_on?->toDateString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function unitCounts(?User $user): array
    {
        $rows = $this->dashboardUnitQuery($user)
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count);

        return [
            'total' => (int) $rows->sum(),
            'available' => (int) ($rows['available'] ?? 0),
            'hold' => (int) (($rows['reserved'] ?? 0) + ($rows['on_hold'] ?? 0)),
            'booked' => (int) ($rows['booked'] ?? 0),
            'sold' => (int) (($rows['registered'] ?? 0) + ($rows['handed_over'] ?? 0)),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function funnelRows(?User $user): array
    {
        $leadCount = (int) $this->dashboardLeadQuery($user)->count();
        $qualified = (int) $this->dashboardLeadQualificationQuery($user)->where('status', 'qualified')->count();
        $visits = (int) $this->dashboardSiteVisitQuery($user)->count();
        $completedVisits = (int) $this->dashboardSiteVisitQuery($user)->where('status', 'completed')->count();
        $negotiation = (int) $this->dashboardLeadQuery($user)->where('stage', 'Negotiation')->count();
        $booked = (int) $this->dashboardBookingQuery($user)->whereIn('status', ['confirmed', 'agreement_pending', 'registered'])->count();
        $registered = (int) $this->dashboardBookingQuery($user)->where('status', 'registered')->count();

        return [
            ['stage' => 'Total Leads', 'n' => $leadCount, 'color' => '#6366f1'],
            ['stage' => 'Verified', 'n' => max($qualified, $completedVisits), 'color' => '#7c3aed'],
            ['stage' => 'Qualified', 'n' => $qualified, 'color' => '#2570eb'],
            ['stage' => 'Site Visit', 'n' => $visits, 'color' => '#0ea5a4'],
            ['stage' => 'Negotiation', 'n' => $negotiation, 'color' => '#e08600'],
            ['stage' => 'Booked', 'n' => $booked, 'color' => '#15a657'],
            ['stage' => 'Registered', 'n' => $registered, 'color' => '#15803d'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function salesFunnelMetrics(?User $user): ?array
    {
        if (! $user || $this->isBuyerPortalUser($user)) {
            return null;
        }

        if (! $this->isPartnerPortalUser($user) && ! $user->hasPermission('crm.view') && ! $user->hasPermission('crm.manage')) {
            return null;
        }

        $leads = $this->dashboardLeadQuery($user)
            ->with([
                'project:id,code,name',
                'booking:id,lead_id,status,booked_on,net_receivable',
                'qualifications' => fn ($query) => $query->orderBy('created_at'),
                'siteVisits' => fn ($query) => $query->orderBy('scheduled_at'),
                'activities' => fn ($query) => $query->orderBy('activity_at'),
            ])
            ->get();

        $funnel = $this->funnelRows($user);
        $totalLeads = $leads->count();
        $bookingStatuses = ['confirmed', 'agreement_pending', 'registered'];
        $bookedLeads = $leads->filter(fn (Lead $lead): bool => $lead->booking !== null && in_array($lead->booking->status, $bookingStatuses, true));
        $completedVisitLeadIds = $leads
            ->flatMap(fn (Lead $lead) => $lead->siteVisits->where('status', 'completed')->pluck('lead_id'))
            ->unique()
            ->count();

        $dropOffs = collect($funnel)->map(function (array $row, int $index) use ($funnel): ?array {
            if ($index === 0) {
                return null;
            }

            $previous = max((int) ($funnel[$index - 1]['n'] ?? 0), 0);
            $current = max((int) ($row['n'] ?? 0), 0);

            if ($previous === 0) {
                return null;
            }

            $drop = max(0, round((1 - ($current / $previous)) * 100));

            return [
                'label' => ($funnel[$index - 1]['stage'] ?? 'Previous').' → '.$row['stage'],
                'value' => $drop,
            ];
        })->filter()->sortByDesc('value')->values();

        $palette = ['var(--green)', 'var(--accent)', 'var(--blue)', 'var(--orange)', 'var(--violet)', 'var(--red)', 'var(--slate)'];

        $lostReasons = $leads
            ->filter(fn (Lead $lead): bool => $lead->status === 'lost' || $lead->stage === 'Lost' || $lead->disposition_outcome === 'lost')
            ->groupBy(fn (Lead $lead): string => trim((string) $lead->disposition_reason) !== '' ? (string) $lead->disposition_reason : 'Reason not captured')
            ->map(fn ($items, string $reason): array => [
                'label' => $reason,
                'value' => $items->count(),
            ])
            ->sortByDesc('value')
            ->values()
            ->take(6)
            ->map(fn (array $row, int $index): array => $row + ['color' => $palette[$index % count($palette)]])
            ->all();

        $sourceConversion = $leads
            ->groupBy(fn (Lead $lead): string => trim((string) $lead->source) !== '' ? (string) $lead->source : 'Unknown')
            ->map(function ($items, string $source) use ($bookingStatuses): array {
                $leadCount = $items->count();
                $bookingCount = $items->filter(fn (Lead $lead): bool => $lead->booking !== null && in_array($lead->booking->status, $bookingStatuses, true))->count();
                $conversion = $leadCount > 0 ? round(($bookingCount / $leadCount) * 100, 1) : 0.0;

                return [
                    'label' => $source,
                    'value' => $conversion,
                    'display' => $conversion.'%',
                    'leads' => $leadCount,
                    'bookings' => $bookingCount,
                ];
            })
            ->sortByDesc('value')
            ->values()
            ->take(6)
            ->map(fn (array $row, int $index): array => $row + ['color' => $palette[$index % count($palette)]])
            ->all();

        $projectBookingRates = collect($this->dashboardProjects($user))
            ->map(function (array $project): array {
                $units = max((int) ($project['units'] ?? 0), 0);
                $sold = max((int) ($project['sold'] ?? 0), 0);
                $rate = $units > 0 ? round(($sold / $units) * 100) : 0;

                return [
                    'label' => explode(' ', (string) ($project['name'] ?? $project['code'] ?? 'Project'))[0],
                    'value' => $rate,
                    'display' => $rate.'%',
                    'color' => $project['color'] ?? 'var(--accent)',
                ];
            })
            ->values()
            ->all();

        $averageDays = function (callable $startResolver, callable $endResolver) use ($leads): ?float {
            $durations = [];

            foreach ($leads as $lead) {
                $start = $startResolver($lead);
                $end = $endResolver($lead);

                if ($start && $end && $end->greaterThanOrEqualTo($start)) {
                    $durations[] = $start->diffInHours($end) / 24;
                }
            }

            return $durations === [] ? null : round(array_sum($durations) / count($durations), 1);
        };

        $firstQualified = fn (Lead $lead) => $lead->qualifications->firstWhere('status', 'qualified')?->created_at;
        $firstVisit = fn (Lead $lead) => $lead->siteVisits->first()?->scheduled_at;
        $firstCompletedVisit = fn (Lead $lead) => $lead->siteVisits->firstWhere('status', 'completed')?->completed_at
            ?? $lead->siteVisits->firstWhere('status', 'completed')?->scheduled_at;
        $firstNegotiation = fn (Lead $lead) => $lead->activities->firstWhere('new_stage', 'Negotiation')?->activity_at
            ?? (in_array($lead->stage, ['Negotiation', 'Booked'], true) ? $lead->updated_at : null);
        $bookingDate = fn (Lead $lead) => $lead->booking?->booked_on?->startOfDay() ?? $lead->booking?->created_at;

        $durationInputs = [
            ['label' => 'Lead → Qualified', 'value' => $averageDays(fn (Lead $lead) => $lead->created_at, $firstQualified), 'color' => 'var(--accent)'],
            ['label' => 'Qualified → Visit', 'value' => $averageDays($firstQualified, $firstVisit), 'color' => 'var(--orange)'],
            ['label' => 'Visit → Negotiation', 'value' => $averageDays($firstCompletedVisit, $firstNegotiation), 'color' => 'var(--blue)'],
            ['label' => 'Negotiation → Booking', 'value' => $averageDays($firstNegotiation, $bookingDate), 'color' => 'var(--red)'],
        ];

        $stageDurations = collect($durationInputs)
            ->filter(fn (array $row): bool => $row['value'] !== null)
            ->map(fn (array $row): array => $row + ['display' => $row['value'].' days'])
            ->values()
            ->all();

        $leadToBooking = $totalLeads > 0 ? round(($bookedLeads->count() / $totalLeads) * 100, 1) : 0.0;
        $visitToBooking = $completedVisitLeadIds > 0 ? round(($bookedLeads->count() / $completedVisitLeadIds) * 100, 1) : 0.0;

        return [
            'source' => 'laravel-sqlite',
            'generated_at' => now()->toISOString(),
            'funnel' => $funnel,
            'summary' => [
                'total_leads' => $totalLeads,
                'bookings' => $bookedLeads->count(),
                'completed_visit_leads' => $completedVisitLeadIds,
                'booking_conversion_percent' => $leadToBooking,
                'visit_to_booking_percent' => $visitToBooking,
                'biggest_dropoff_label' => $dropOffs->first()['label'] ?? null,
                'biggest_dropoff_percent' => $dropOffs->first()['value'] ?? null,
            ],
            'lost_reasons' => $lostReasons,
            'source_conversion' => $sourceConversion,
            'project_booking_rates' => $projectBookingRates,
            'stage_durations' => $stageDurations,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function salesPerformanceMetrics(?User $user): ?array
    {
        if (! $user || $this->isBuyerPortalUser($user)) {
            return null;
        }

        if (! $this->isPartnerPortalUser($user) && ! $user->hasPermission('crm.view') && ! $user->hasPermission('crm.manage')) {
            return null;
        }

        $isPartnerScope = $this->isPartnerPortalUser($user);
        $bookingStatuses = ['confirmed', 'agreement_pending', 'registered'];

        $leads = $this->dashboardLeadQuery($user)
            ->with([
                'owner:id,name',
                'partner:id,code,name',
                'project:id,code,name',
                'qualifications:id,lead_id,status',
                'siteVisits:id,lead_id,status',
                'activities:id,lead_id,activity_type,activity_at',
                'booking:id,lead_id,booked_by_user_id,partner_id,status,net_receivable',
                'booking.collectionReceipts' => fn ($query) => $query->where('status', 'approved'),
            ])
            ->get();

        $grouped = $isPartnerScope
            ? $leads->groupBy(fn (Lead $lead): string => (string) ($lead->partner_id ?? 0))
            : $leads->groupBy(fn (Lead $lead): string => (string) ($lead->owner_user_id ?? 0));

        $palette = ['var(--green)', 'var(--accent)', 'var(--blue)', 'var(--orange)', 'var(--violet)', 'var(--red)'];

        $rows = $grouped
            ->map(function ($items, string $groupKey) use ($bookingStatuses, $isPartnerScope): array {
                /** @var \Illuminate\Support\Collection<int, Lead> $items */
                $first = $items->first();
                $name = $isPartnerScope
                    ? ($first?->partner?->name ?? 'Unassigned partner')
                    : ($first?->owner?->name ?? 'Unassigned owner');
                $projectLabel = $items
                    ->pluck('project.name')
                    ->filter()
                    ->unique()
                    ->take(2)
                    ->implode(', ');
                $assigned = $items->count();
                $verified = $items->filter(fn (Lead $lead): bool => $lead->qualifications->contains('status', 'qualified'))->count();
                $visits = $items->sum(fn (Lead $lead): int => $lead->siteVisits->count());
                $bookedLeads = $items->filter(fn (Lead $lead): bool => $lead->booking !== null && in_array($lead->booking->status, $bookingStatuses, true));
                $bookings = $bookedLeads->count();
                $revenue = (float) $bookedLeads->sum(fn (Lead $lead): float => (float) $lead->booking?->net_receivable);
                $collections = (float) $bookedLeads->sum(fn (Lead $lead): float => (float) $lead->booking?->collectionReceipts->sum('amount'));
                $expected = (float) $items->sum(fn (Lead $lead): float => (float) $lead->expected_value);
                $conversion = $assigned > 0 ? round(($bookings / $assigned) * 100, 1) : 0.0;
                $targetPercent = $expected > 0 ? (int) min(round(($revenue / $expected) * 100), 999) : 0;
                $responseHours = $items
                    ->map(function (Lead $lead): ?float {
                        $firstActivity = $lead->activities
                            ->whereNotIn('activity_type', ['created', 'campaign_response'])
                            ->sortBy('activity_at')
                            ->first();

                        if (! $firstActivity?->activity_at || ! $lead->created_at || $firstActivity->activity_at->lessThan($lead->created_at)) {
                            return null;
                        }

                        return $lead->created_at->diffInMinutes($firstActivity->activity_at) / 60;
                    })
                    ->filter(fn (?float $hours): bool => $hours !== null);
                $missedFollowUps = $items
                    ->filter(fn (Lead $lead): bool => $lead->follow_up_at !== null && $lead->follow_up_at->isPast() && ! in_array($lead->status, ['won', 'lost'], true))
                    ->count();

                return [
                    'group_key' => $groupKey,
                    'name' => $name,
                    'proj' => $projectLabel !== '' ? $projectLabel : 'No project',
                    'assigned' => $assigned,
                    'verified' => $verified,
                    'visits' => $visits,
                    'bookings' => $bookings,
                    'revenue' => round($revenue, 2),
                    'collection' => round($collections, 2),
                    'rev' => '₹'.$this->toCrore($revenue).' Cr',
                    'coll' => '₹'.$this->toCrore($collections).' Cr',
                    'conv' => $conversion,
                    'resp' => $responseHours->isNotEmpty() ? round($responseHours->avg(), 1).'h' : '—',
                    'missed' => $missedFollowUps,
                    'tpct' => $targetPercent,
                    'inc' => $targetPercent >= 100 ? 'Eligible' : '—',
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->take(10)
            ->map(fn (array $row, int $index): array => $row + ['color' => $palette[$index % count($palette)]])
            ->all();

        $totalAssigned = collect($rows)->sum('assigned');
        $totalBookings = collect($rows)->sum('bookings');
        $totalRevenue = (float) collect($rows)->sum('revenue');
        $avgConversion = $totalAssigned > 0 ? round(($totalBookings / $totalAssigned) * 100, 1) : 0.0;
        $avgTarget = collect($rows)->isNotEmpty() ? round(collect($rows)->avg('tpct')) : 0;
        $eligibleCount = collect($rows)->where('tpct', '>=', 100)->count();
        $top = collect($rows)->first();

        return [
            'source' => 'laravel-sqlite',
            'generated_at' => now()->toISOString(),
            'scope' => [
                'type' => $isPartnerScope ? 'partner' : 'crm',
            ],
            'summary' => [
                'top_performer' => $top['name'] ?? 'No performer',
                'top_performer_sub' => $top ? (($top['tpct'] ?? 0).'% of target · '.$top['rev']) : 'No sales data available',
                'team_target_achievement' => $avgTarget,
                'avg_conversion' => $avgConversion,
                'eligible_count' => $eligibleCount,
                'row_count' => count($rows),
                'total_revenue' => round($totalRevenue, 2),
                'total_revenue_crore' => $this->toCrore($totalRevenue),
            ],
            'sales_rows' => $rows,
            'revenue_leaderboard' => collect($rows)
                ->map(fn (array $row): array => [
                    'label' => $row['name'],
                    'value' => $this->toCrore($row['revenue']),
                    'display' => $row['rev'],
                    'color' => $row['color'],
                ])
                ->values()
                ->all(),
            'target_chart' => collect($rows)
                ->map(fn (array $row): array => [
                    'label' => str($row['name'])->before(' ')->toString(),
                    'value' => $row['tpct'],
                    'color' => $row['tpct'] >= 100 ? 'var(--green)' : ($row['tpct'] >= 80 ? 'var(--orange)' : 'var(--red)'),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function approvalRows(?User $user): array
    {
        return collect(app(ApprovalCenterService::class)->dashboardRows($user, $this->selectedProjectId))
            ->take(8)
            ->map(fn (array $row): array => [
                'id' => $row['number'] ?? $row['id'],
                'record_id' => $row['record_id'],
                'type' => $row['type'],
                'b' => $row['b'] ?? 'b-blue',
                'desc' => $row['description'],
                'who' => $row['raised_by'],
                'amt' => $row['amount_display'],
                'age' => $row['age'],
                'pr' => $row['priority'],
                'status' => $row['status'],
                'source_module' => $row['source_module'],
                'can_approve' => $row['can_approve'],
                'can_reject' => $row['can_reject'] ?? false,
                'approve_url' => $row['approve_url'],
                'reject_url' => $row['reject_url'] ?? null,
                'approve_payload_key' => $row['approve_payload_key'],
                'reject_payload_key' => $row['reject_payload_key'] ?? $row['approve_payload_key'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function approvalInboxOptions(?User $user): ?array
    {
        return app(ApprovalCenterService::class)->bootstrapOptions($user, $this->selectedProjectId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function approvalInboxRows(?User $user): array
    {
        if (! $user || $this->isExternalDashboardUser($user)) {
            return [];
        }

        $companyId = $this->dashboardCompanyScope($user);
        $rows = collect();

        if ($user->can('viewAny', PurchaseRequisition::class)) {
            $this->scope(PurchaseRequisition::query(), $companyId)
                ->with(['requestedBy:id,name,email'])
                ->where('status', 'submitted')
                ->latest()
                ->limit(10)
                ->get()
                ->each(function (PurchaseRequisition $item) use ($rows, $user): void {
                    $canApprove = $user->can('approve', $item);

                    $rows->push([
                        'id' => 'purchase_requisition_'.$item->id,
                        'record_id' => $item->id,
                        'number' => $item->requisition_number,
                        'type' => 'Purchase Requisition',
                        'source_module' => 'procurement',
                        'b' => 'b-blue',
                        'description' => $item->purpose,
                        'raised_by' => $item->requestedBy?->name ?? 'System',
                        'raised_by_email' => $item->requestedBy?->email,
                        'amount_value' => round((float) $item->estimated_total, 2),
                        'amount_display' => $this->currency($item->estimated_total),
                        'age' => $item->created_at?->diffForHumans(short: true) ?? 'new',
                        'priority' => $item->priority === 'urgent' ? 'high' : 'med',
                        'status' => $item->status,
                        'created_at' => $item->created_at?->toISOString(),
                        'can_approve' => $canApprove,
                        'approve_url' => $canApprove ? route('procurement.requisitions.approve', $item, false) : null,
                        'approve_payload_key' => 'note',
                    ]);
                });
        }

        if ($user->can('viewAny', PurchaseOrder::class)) {
            $this->scope(PurchaseOrder::query(), $companyId)
                ->with(['vendor:id,name', 'createdBy:id,name,email'])
                ->where('status', 'draft')
                ->latest()
                ->limit(10)
                ->get()
                ->each(function (PurchaseOrder $item) use ($rows, $user): void {
                    $canApprove = $user->can('approve', $item);

                    $rows->push([
                        'id' => 'purchase_order_'.$item->id,
                        'record_id' => $item->id,
                        'number' => $item->po_number,
                        'type' => 'Purchase Order',
                        'source_module' => 'procurement',
                        'b' => 'b-violet',
                        'description' => 'PO approval for '.($item->vendor?->name ?? 'vendor'),
                        'raised_by' => $item->createdBy?->name ?? 'System',
                        'raised_by_email' => $item->createdBy?->email,
                        'amount_value' => round((float) $item->total_amount, 2),
                        'amount_display' => $this->currency($item->total_amount),
                        'age' => $item->created_at?->diffForHumans(short: true) ?? 'new',
                        'priority' => 'high',
                        'status' => $item->status,
                        'created_at' => $item->created_at?->toISOString(),
                        'can_approve' => $canApprove,
                        'approve_url' => $canApprove ? route('procurement.purchase-orders.approve', $item, false) : null,
                        'approve_payload_key' => 'note',
                    ]);
                });
        }

        if ($user->can('viewAny', FinancialVoucher::class)) {
            $this->scope(FinancialVoucher::query(), $companyId)
                ->with(['createdBy:id,name,email'])
                ->where('status', 'submitted')
                ->latest()
                ->limit(10)
                ->get()
                ->each(function (FinancialVoucher $item) use ($rows, $user): void {
                    $canApprove = $user->can('approve', $item);

                    $rows->push([
                        'id' => 'financial_voucher_'.$item->id,
                        'record_id' => $item->id,
                        'number' => $item->voucher_number,
                        'type' => 'Financial Voucher',
                        'source_module' => 'finance',
                        'b' => 'b-green',
                        'description' => $item->narration,
                        'raised_by' => $item->createdBy?->name ?? 'System',
                        'raised_by_email' => $item->createdBy?->email,
                        'amount_value' => round((float) $item->total_debit, 2),
                        'amount_display' => $this->currency($item->total_debit),
                        'age' => $item->created_at?->diffForHumans(short: true) ?? 'new',
                        'priority' => 'med',
                        'status' => $item->status,
                        'created_at' => $item->created_at?->toISOString(),
                        'can_approve' => $canApprove,
                        'approve_url' => $canApprove ? route('finance.vouchers.approve', $item, false) : null,
                        'approve_payload_key' => 'note',
                    ]);
                });
        }

        if ($user->can('viewAny', LeaveRequest::class)) {
            $this->scope(LeaveRequest::query(), $companyId)
                ->with(['requestedBy:id,name,email', 'employee:id,name'])
                ->where('status', 'submitted')
                ->latest()
                ->limit(10)
                ->get()
                ->each(function (LeaveRequest $item) use ($rows, $user): void {
                    $canApprove = $user->can('approve', $item);

                    $rows->push([
                        'id' => 'leave_request_'.$item->id,
                        'record_id' => $item->id,
                        'number' => $item->request_number,
                        'type' => 'Leave Approval',
                        'source_module' => 'hr',
                        'b' => 'b-slate',
                        'description' => 'Leave request for '.$item->requested_days.' day(s)',
                        'raised_by' => $item->requestedBy?->name ?? $item->employee?->name ?? 'Employee',
                        'raised_by_email' => $item->requestedBy?->email,
                        'amount_value' => 0.0,
                        'amount_display' => '—',
                        'age' => $item->created_at?->diffForHumans(short: true) ?? 'new',
                        'priority' => 'low',
                        'status' => $item->status,
                        'created_at' => $item->created_at?->toISOString(),
                        'can_approve' => $canApprove,
                        'approve_url' => $canApprove ? route('hr.leave-requests.approve', $item, false) : null,
                        'approve_payload_key' => 'decision_note',
                    ]);
                });
        }

        return $rows
            ->sortByDesc(fn (array $row): string => (string) ($row['created_at'] ?? ''))
            ->take(25)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function alertRows(?User $user): array
    {
        $lowInventory = $this->dashboardUnitQuery($user)->where('status', 'available')->count() === 0;
        $openFollowUps = $this->dashboardLeadQuery($user)
            ->whereNotNull('follow_up_at')
            ->where('follow_up_at', '<', now())
            ->whereNotIn('status', ['won', 'lost'])
            ->count();

        return [
            ['icon' => 'shield', 'color' => 'var(--blue)', 'title' => 'Live business dashboard', 'detail' => 'KPIs are calculated from current business records.'],
            ['icon' => $lowInventory ? 'alert' : 'box', 'color' => $lowInventory ? 'var(--red)' : 'var(--green)', 'title' => 'Inventory availability', 'detail' => $lowInventory ? 'No available units found in current scope.' : 'Available units exist in current scope.'],
            ['icon' => 'clock', 'color' => $openFollowUps > 0 ? 'var(--orange)' : 'var(--green)', 'title' => 'Lead follow-up SLA', 'detail' => $openFollowUps.' overdue follow-up(s) in current scope.'],
        ];
    }

    private function approvedSpend(?User $user): float
    {
        if ($this->isExternalDashboardUser($user)) {
            return 0;
        }

        $companyId = $this->dashboardCompanyScope($user);

        return (float) $this->scope(PurchaseOrder::query(), $companyId)
            ->whereIn('status', ['approved', 'partially_received', 'received'])
            ->sum('total_amount')
            + (float) $this->scope(ContractorMeasurement::query(), $companyId)
                ->where('status', 'approved')
                ->sum('certified_total');
    }

    /**
     * @param array<int, array<string, mixed>> $projectRows
     */
    private function budgetVariance(array $projectRows): float
    {
        $budget = collect($projectRows)->sum('budget');
        $spent = collect($projectRows)->sum('spent');

        if ($budget <= 0) {
            return 0;
        }

        return round(($spent - $budget) / $budget * 100, 1);
    }

    /**
     * @return array{branches: int, projects: int, employees: int, leads: int}
     */
    private function companyCounts(Company $company, ?User $user): array
    {
        if ($this->isExternalDashboardUser($user)) {
            return [
                'branches' => 0,
                'projects' => (int) $this->dashboardProjectQuery($user)
                    ->where('company_id', $company->id)
                    ->count(),
                'employees' => 0,
                'leads' => (int) $this->dashboardLeadQuery($user)
                    ->where('company_id', $company->id)
                    ->count(),
            ];
        }

        return [
            'branches' => (int) $company->branches()->count(),
            'projects' => (int) $company->projects()->count(),
            'employees' => (int) $company->employees()->count(),
            'leads' => (int) $company->leads()->count(),
        ];
    }

    /**
     * @return Builder<Project>
     */
    private function dashboardProjectQuery(?User $user): Builder
    {
        return $this->applyProjectActorScope(Project::query(), $user)
            ->when($this->selectedProjectId, fn (Builder $query): Builder => $query->whereKey($this->selectedProjectId));
    }

    /**
     * @return Builder<ProjectUnit>
     */
    private function dashboardUnitQuery(?User $user): Builder
    {
        $projectIds = $this->visibleProjectIds($user);

        return ProjectUnit::query()
            ->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('project_id', $projectIds ?: [0]))
            ->when($this->selectedProjectId, fn (Builder $query): Builder => $query->where('project_id', $this->selectedProjectId));
    }

    /**
     * @return Builder<Booking>
     */
    private function dashboardBookingQuery(?User $user): Builder
    {
        return $this->constrainDashboardBookingQuery(Booking::query(), $user);
    }

    /**
     * @return Builder<CollectionReceipt>
     */
    private function dashboardCollectionQuery(?User $user): Builder
    {
        return $this->constrainDashboardCollectionQuery(CollectionReceipt::query(), $user);
    }

    /**
     * @return Builder<Lead>
     */
    private function dashboardLeadQuery(?User $user): Builder
    {
        return $this->constrainDashboardLeadQuery(Lead::query(), $user);
    }

    /**
     * @return Builder<LeadQualification>
     */
    private function dashboardLeadQualificationQuery(?User $user): Builder
    {
        return $this->applyDashboardPeriod(LeadQualification::query(), 'created_at')
            ->when(
                $this->isPartnerPortalUser($user) || $this->isBuyerPortalUser($user),
                fn (Builder $query) => $query->whereHas('lead', fn (Builder $leadQuery) => $this->constrainDashboardLeadQuery($leadQuery, $user)),
                fn (Builder $query) => $this->scope($query, $this->dashboardCompanyScope($user)),
            )
            ->when($this->selectedProjectId, fn (Builder $query): Builder => $query->whereHas('lead', fn (Builder $leadQuery): Builder => $leadQuery->where('project_id', $this->selectedProjectId)));
    }

    /**
     * @return Builder<SiteVisit>
     */
    private function dashboardSiteVisitQuery(?User $user): Builder
    {
        return $this->applyDashboardPeriod(SiteVisit::query(), 'scheduled_at')
            ->when(
                $this->isPartnerPortalUser($user),
                fn (Builder $query) => $query->whereHas('lead', fn (Builder $leadQuery) => $this->constrainDashboardLeadQuery($leadQuery, $user)),
                fn (Builder $query) => $this->isBuyerPortalUser($user)
                    ? $query->where('customer_id', $user?->customer?->id ?? 0)
                    : $this->scope($query, $this->dashboardCompanyScope($user)),
            )
            ->when($this->selectedProjectId, fn (Builder $query): Builder => $query->where('project_id', $this->selectedProjectId));
    }

    /**
     * @param Builder<Project> $query
     * @return Builder<Project>
     */
    private function applyProjectActorScope(Builder $query, ?User $user): Builder
    {
        $projectIds = $this->visibleProjectIds($user);

        return $query->when(is_array($projectIds), fn (Builder $query) => $query->whereIn('id', $projectIds ?: [0]));
    }

    /**
     * @param mixed $query
     * @return mixed
     */
    private function constrainDashboardBookingQuery($query, ?User $user)
    {
        $query = $this->applyDashboardPeriod($query, 'booked_on');

        if ($this->isPartnerPortalUser($user)) {
            $partnerIds = $this->partnerIdsForUser($user);

            return $query->whereIn('partner_id', $partnerIds ?: [0])
                ->when($this->selectedProjectId, fn (Builder $query): Builder => $query->where('project_id', $this->selectedProjectId));
        }

        if ($this->isBuyerPortalUser($user)) {
            return $query->where('customer_id', $user?->customer?->id ?? 0)
                ->when($this->selectedProjectId, fn (Builder $query): Builder => $query->where('project_id', $this->selectedProjectId));
        }

        return $this->scope($query, $this->dashboardCompanyScope($user))
            ->when($this->selectedProjectId, fn (Builder $query): Builder => $query->where('project_id', $this->selectedProjectId));
    }

    /**
     * @param mixed $query
     * @return mixed
     */
    private function constrainDashboardCollectionQuery($query, ?User $user)
    {
        $query = $this->applyDashboardPeriod($query, 'receipt_date');

        if ($this->isPartnerPortalUser($user)) {
            $partnerIds = $this->partnerIdsForUser($user);

            return $query->whereHas('booking', fn (Builder $bookingQuery) => $bookingQuery->whereIn('partner_id', $partnerIds ?: [0]))
                ->when($this->selectedProjectId, fn (Builder $query): Builder => $query->where('project_id', $this->selectedProjectId));
        }

        if ($this->isBuyerPortalUser($user)) {
            return $query->where('customer_id', $user?->customer?->id ?? 0)
                ->when($this->selectedProjectId, fn (Builder $query): Builder => $query->where('project_id', $this->selectedProjectId));
        }

        return $this->scope($query, $this->dashboardCompanyScope($user))
            ->when($this->selectedProjectId, fn (Builder $query): Builder => $query->where('project_id', $this->selectedProjectId));
    }

    /**
     * @param mixed $query
     * @return mixed
     */
    private function constrainDashboardLeadQuery($query, ?User $user)
    {
        $query = $this->applyDashboardPeriod($query, 'created_at');

        if ($this->isPartnerPortalUser($user)) {
            $partnerIds = $this->partnerIdsForUser($user);

            return $query->whereIn('partner_id', $partnerIds ?: [0])
                ->when($this->selectedProjectId, fn (Builder $query): Builder => $query->where('project_id', $this->selectedProjectId));
        }

        if ($this->isBuyerPortalUser($user)) {
            return $query->where('customer_id', $user?->customer?->id ?? 0)
                ->when($this->selectedProjectId, fn (Builder $query): Builder => $query->where('project_id', $this->selectedProjectId));
        }

        return $this->scope($query, $this->dashboardCompanyScope($user))
            ->when($this->selectedProjectId, fn (Builder $query): Builder => $query->where('project_id', $this->selectedProjectId));
    }

    /**
     * @param mixed $query
     * @return mixed
     */
    private function applyDashboardPeriod($query, string $column = 'created_at')
    {
        $from = $this->dashboardPeriod['date_from'] ?? null;
        $to = $this->dashboardPeriod['date_to'] ?? null;

        if (! is_string($from) || ! is_string($to)) {
            return $query;
        }

        return $query
            ->whereDate($column, '>=', $from)
            ->whereDate($column, '<=', $to);
    }

    /**
     * @param mixed $query
     * @return mixed
     */
    private function scope($query, ?int $companyId)
    {
        return $companyId !== null ? $query->where('company_id', $companyId) : $query;
    }

    private function toCrore(float|int|string|null $amount): float
    {
        return round(((float) $amount) / 10000000, 2);
    }

    private function currency(float|int|string|null $amount): string
    {
        return '₹'.number_format((float) $amount, 0, '.', ',');
    }

    private function formatLakhs(float|int|string|null $amount): string
    {
        return '₹'.number_format(((float) $amount) / 100000, 1, '.', ',').' L';
    }

    private function paymentGatewayMode(?string $provider): string
    {
        return $this->isSimulatedPaymentGatewayProvider($provider) ? 'simulated' : 'configured';
    }

    private function paymentGatewayLabel(?string $provider): string
    {
        $provider = strtolower(trim((string) $provider));

        if ($this->isSimulatedPaymentGatewayProvider($provider)) {
            return 'Internal simulated gateway';
        }

        return str($provider)->replace(['_', '-'], ' ')->title()->toString().' gateway';
    }

    private function isSimulatedPaymentGatewayProvider(?string $provider): bool
    {
        $provider = strtolower(trim((string) $provider));

        return $provider === ''
            || in_array($provider, ['prototype', 'demo', 'mock', 'sandbox', 'simulated', 'simulation'], true);
    }

    private function projectColor(string $code): string
    {
        return match (true) {
            str_contains($code, 'SKY') => '#F6740C',
            str_contains($code, 'GRN') => '#15a657',
            str_contains($code, 'ORC') => '#e08600',
            str_contains($code, 'LKV') => '#7c3aed',
            str_contains($code, 'MTO') => '#2570eb',
            default => '#64748b',
        };
    }

    private function leadBadge(?string $stage, ?string $status): string
    {
        if ($status === 'won' || $stage === 'Booked') {
            return 'b-green';
        }

        if ($status === 'lost' || $stage === 'Lost') {
            return 'b-red';
        }

        if ($status === 'on_hold') {
            return 'b-orange';
        }

        return match ($stage) {
            'Qualified' => 'b-accent',
            'Site Visit Planned', 'Site Visit Scheduled', 'Site Visit Done', 'Follow-up' => 'b-orange',
            'Negotiation' => 'b-violet',
            default => 'b-slate',
        };
    }

    private function leadScore(?string $stage, ?string $status): int
    {
        if ($status === 'won' || $stage === 'Booked') {
            return 95;
        }

        if ($status === 'lost' || $stage === 'Lost') {
            return 15;
        }

        return match ($stage) {
            'Negotiation' => 82,
            'Site Visit Done' => 76,
            'Site Visit Planned', 'Site Visit Scheduled' => 68,
            'Qualified' => 62,
            'Follow-up' => 44,
            default => 35,
        };
    }

    private function leadActivityIcon(?string $activityType): string
    {
        return match ($activityType) {
            'created' => 'plus',
            'stage_change' => 'refresh',
            'disposition' => 'flag',
            'campaign_response' => 'mega',
            'call' => 'headset',
            'site_visit' => 'calendar',
            default => 'activity',
        };
    }

    private function leadActivityColor(?string $activityType, ?string $outcome): string
    {
        if (in_array($outcome, ['lost', 'invalid', 'duplicate', 'not_interested'], true)) {
            return 'var(--red)';
        }

        if (in_array($outcome, ['qualified', 'won', 'lead_created'], true)) {
            return 'var(--green)';
        }

        return match ($activityType) {
            'created' => 'var(--slate)',
            'stage_change' => 'var(--accent)',
            'disposition' => 'var(--orange)',
            'campaign_response' => 'var(--blue)',
            default => 'var(--accent)',
        };
    }

    private function dashboardCompanyScope(?User $user): ?int
    {
        if (! $user) {
            return null;
        }

        $companyScope = app(CompanyScopeService::class);

        if (config('builder360.single_company.enabled', true)) {
            $companyId = $companyScope->companyIdFor($user);

            return $companyId;
        }

        if ($companyScope->hasGlobalScope($user)) {
            return null;
        }

        if ($this->isExternalDashboardUser($user)) {
            $companyIds = $this->visibleCompanyIds($user);

            return is_array($companyIds) && count($companyIds) === 1 ? $companyIds[0] : null;
        }

        return $companyScope->companyIdFor($user);
    }

    private function dashboardScopeLevel(?User $user): string
    {
        if (! $user) {
            return 'guest';
        }

        if ($this->isPartnerPortalUser($user)) {
            return 'partner';
        }

        if ($this->isBuyerPortalUser($user)) {
            return 'buyer';
        }

        if (config('builder360.single_company.enabled', true)) {
            return 'company';
        }

        if (app(CompanyScopeService::class)->hasGlobalScope($user)) {
            return 'global';
        }

        return 'company';
    }

    private function isExternalDashboardUser(?User $user): bool
    {
        return $this->isPartnerPortalUser($user) || $this->isBuyerPortalUser($user);
    }

    private function canViewRoleCatalogue(?User $user): bool
    {
        return $user?->hasPermission('*') === true
            || $user?->hasPermission('roles.view') === true
            || $user?->hasPermission('roles.manage') === true
            || $user?->hasPermission('users.view') === true
            || $user?->hasPermission('users.manage') === true;
    }

    private function canSeeModule(?User $user, ErpModule $module): bool
    {
        if (! $user) {
            return false;
        }

        $slug = $module->slug;

        if ($slug === 'ess' && ! Employee::query()->where('user_id', $user->id)->exists()) {
            return false;
        }

        if ($this->isPartnerPortalUser($user)) {
            return ($module->route ?: $slug) === 'partner';
        }

        if (($module->route ?: $slug) === 'partner') {
            return false;
        }

        if (($module->route ?: $slug) === 'buyer') {
            return $this->isBuyerPortalUser($user);
        }

        if (($module->route ?: $slug) === 'chat' && ! app(ChatAccessService::class)->canView($user)) {
            return false;
        }

        if ($this->isBuyerPortalUser($user)) {
            return false;
        }

        $policyTarget = $this->modulePolicyTarget($module->route ?: $slug);
        if ($policyTarget !== null && ! $user->can('viewAny', $policyTarget)) {
            return false;
        }

        if ($user->hasPermission('*')) {
            return true;
        }

        $requiredPermissions = $module->required_permissions ?? [];
        $requiredPermissions = $requiredPermissions === [] ? $this->defaultModulePermissions($slug) : $requiredPermissions;

        if ($requiredPermissions === []) {
            return $slug === 'dashboard';
        }

        foreach ($requiredPermissions as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return the same policy target used by a module's primary page. Modules
     * with aggregate request authorization continue through their configured
     * permission rules below.
     *
     * @return class-string|null
     */
    private function modulePolicyTarget(string $route): ?string
    {
        return match ($route) {
            'tasks' => WorkTask::class,
            'calendar' => CalendarEvent::class,
            'mailbox' => CollaborationMessage::class,
            'leads', 'funnel', 'performance' => Lead::class,
            'qualification' => LeadQualification::class,
            'sitevisits' => SiteVisit::class,
            'sales' => Booking::class,
            'marketing' => MarketingCampaign::class,
            'collections' => CollectionReceipt::class,
            'projects', 'cost' => Project::class,
            'inventory' => ProjectUnit::class,
            'pricing' => UnitPriceVersion::class,
            'planning' => ConstructionMilestone::class,
            'progress' => DailyProgressReport::class,
            'materials' => StockItem::class,
            'procurement' => PurchaseRequisition::class,
            'vendors' => Vendor::class,
            'contractors' => ContractorBill::class,
            'boq' => BoqItem::class,
            'hr' => Employee::class,
            'payroll' => PayrollRun::class,
            'recruitment' => JobOpening::class,
            'legal' => ReraRegistration::class,
            'documents' => ManagedDocument::class,
            'possession' => PossessionHandover::class,
            'complaints' => ServiceTicket::class,
            'maintenance' => SocietyFormation::class,
            'admin' => User::class,
            default => null,
        };
    }

    /**
     * Role context is a read-only preview, not user impersonation. Keep the
     * preview data but remove drill-downs that the authenticated actor cannot
     * open through server policies.
     *
     * @param  array<string, mixed>  $dashboard
     * @return array<string, mixed>
     */
    private function constrainPreviewDashboardRoutes(array $dashboard, ?User $actor, ?User $effectiveUser): array
    {
        if (! $actor || ! $effectiveUser || (int) $actor->id === (int) $effectiveUser->id) {
            return $dashboard;
        }

        $allowedRoutes = collect($this->moduleGroups($actor, $actor))
            ->flatMap(fn (array $group) => collect($group['items'] ?? [])->pluck('route'))
            ->filter(fn ($route): bool => is_string($route) && $route !== '')
            ->merge(['dashboard', 'notifications', 'profile'])
            ->unique()
            ->values();

        $sanitize = function (array $item) use ($allowedRoutes, &$sanitize): array {
            $route = is_string($item['route'] ?? null) ? str($item['route'])->before('?')->toString() : null;

            if ($route !== null && ! $allowedRoutes->contains($route)) {
                $item['route'] = null;
                $item['route_filter'] = [];
                $item['is_actionable'] = false;
            }

            if (is_array($item['rows'] ?? null)) {
                $item['rows'] = array_map($sanitize, $item['rows']);
            }

            if (is_array($item['mode_rows'] ?? null)) {
                $item['mode_rows'] = collect($item['mode_rows'])
                    ->map(fn ($rows) => is_array($rows) ? array_map($sanitize, $rows) : [])
                    ->all();
            }

            return $item;
        };

        foreach (['stats', 'charts', 'alerts', 'tables', 'sections'] as $collection) {
            $dashboard[$collection] = array_map($sanitize, is_array($dashboard[$collection] ?? null) ? $dashboard[$collection] : []);
        }

        $dashboard['quick_actions'] = array_values(array_filter(
            array_map($sanitize, is_array($dashboard['quick_actions'] ?? null) ? $dashboard['quick_actions'] : []),
            fn (array $action): bool => is_string($action['route'] ?? null) && $action['route'] !== '',
        ));

        if ($dashboard['quick_actions'] === []) {
            $dashboard['quick_actions'][] = [
                'key' => 'dashboard_preview',
                'label' => 'Dashboard Preview',
                'icon' => 'grid',
                'route' => 'dashboard',
                'route_filter' => [],
                'is_actionable' => true,
            ];
        }

        $primaryRoute = is_string($dashboard['primary_route'] ?? null) ? str($dashboard['primary_route'])->before('?')->toString() : null;
        if ($primaryRoute !== null && ! $allowedRoutes->contains($primaryRoute)) {
            $dashboard['primary_route'] = 'dashboard';
            $dashboard['primary_label'] = 'Dashboard Preview';
        }

        return $dashboard;
    }

    /**
     * @return array<int, string>
     */
    private function defaultModulePermissions(string $slug): array
    {
        return match ($slug) {
            'dashboard' => [],
            'approvals' => ['reports.view', 'finance.approve', 'hr.manage', 'construction.manage', 'procurement.manage', 'settings.approve'],
            'reports' => ['reports.view'],
            'leads', 'qualification', 'sitevisits', 'marketing', 'funnel', 'inquiry' => ['crm.view', 'crm.manage'],
            'sales' => ['booking.view', 'booking.manage'],
            'collections' => ['finance.view', 'finance.manage', 'finance.approve'],
            'performance' => ['crm.view', 'crm.manage', 'hr.view', 'hr.manage', 'reports.view'],
            'projects', 'inventory', 'pricing', 'cost' => ['inventory.view', 'booking.view', 'finance.view'],
            'planning', 'progress', 'materials', 'stock-issues', 'procurement', 'purchase-requisitions', 'purchase-orders', 'goods-receipts', 'vendors', 'contractors', 'boq', 'measurements', 'contractor-bills' => ['construction.view', 'construction.manage', 'procurement.view', 'procurement.manage'],
            'hr', 'hr-attendance', 'hr-leave', 'hr-performance', 'hr-confirmation', 'hr-separation', 'hr-assets', 'hr-claims', 'hr-loans', 'hr-helpdesk', 'hr-documents', 'hr-compliance' => ['hr.view', 'hr.manage', 'attendance.view', 'attendance.manage', 'leave.view', 'leave.manage', 'performance.view', 'performance.manage', 'assets.view', 'assets.manage', 'claims.view', 'claims.manage', 'loans.view', 'loans.manage', 'helpdesk.view', 'helpdesk.manage', 'documents.view', 'documents.manage'],
            'ess' => ['employee.self_service'],
            'payroll', 'payroll-structures', 'payroll-components', 'payroll-bank-batches', 'payroll-commissions', 'payroll-tax-documents' => ['payroll.view', 'payroll.manage', 'payroll.approve'],
            'recruitment', 'recruitment-candidates', 'recruitment-interviews', 'recruitment-offers', 'recruitment-sources' => ['recruitment.view', 'recruitment.manage', 'recruitment.approve'],
            'finance', 'finance-vouchers', 'finance-payment-requests', 'finance-gst-entries', 'finance-gst-returns' => ['finance.view', 'finance.manage', 'finance.approve'],
            'legal', 'legal-project-approvals', 'legal-obligations' => ['legal.view', 'legal.manage', 'legal.approve', 'compliance.view', 'compliance.manage'],
            'documents', 'document-categories' => ['documents.view', 'documents.manage', 'documents.approve'],
            'possession', 'possession-snags' => ['possession.view', 'possession.manage', 'possession.approve'],
            'maintenance', 'maintenance-handover-items', 'maintenance-dues' => ['after_sales.view', 'after_sales.manage', 'after_sales.approve'],
            'after-sales', 'after-sales-work-orders' => ['after_sales.view', 'after_sales.manage', 'after_sales.approve'],
            'buyer', 'mobile' => ['crm.view', 'booking.view', 'after_sales.view', 'employee.self_service'],
            'tasks', 'calendar', 'chat', 'mailbox', 'notifications' => ['collaboration.view', 'collaboration.manage', 'employee.self_service'],
            'settings', 'workflows', 'auth', 'data-imports' => ['settings.view', 'settings.manage', 'settings.approve'],
            'scoring' => LogicCenterPermissions::navigation(),
            'administration', 'admin-users', 'admin-roles' => ['users.view', 'users.manage', 'roles.view', 'roles.manage'],
            'audit' => ['audit.view'],
            'partner' => ['partner.portal'],
            default => [],
        };
    }

    /**
     * Returns null for global visibility and an explicit id list for scoped users.
     *
     * @return array<int, int>|null
     */
    private function visibleCompanyIds(?User $user): ?array
    {
        if (! $user) {
            return [];
        }

        $companyScope = app(CompanyScopeService::class);

        if (config('builder360.single_company.enabled', true) && ! $this->isExternalDashboardUser($user)) {
            $companyId = $companyScope->companyIdFor($user);

            return $companyId !== null && $companyId > 0 ? [$companyId] : [];
        }

        if ($companyScope->hasGlobalScope($user)) {
            return null;
        }

        if ($this->isPartnerPortalUser($user)) {
            $partnerIds = $this->partnerIdsForUser($user);

            if ($partnerIds === []) {
                return [];
            }

            $companyIds = collect()
                ->merge(Lead::query()->whereIn('partner_id', $partnerIds)->pluck('company_id'))
                ->merge(Booking::query()->whereIn('partner_id', $partnerIds)->pluck('company_id'))
                ->filter()
                ->unique()
                ->values()
                ->all();

            return array_map('intval', $companyIds);
        }

        if ($this->isBuyerPortalUser($user)) {
            $customer = $user->customer;

            if (! $customer) {
                return [];
            }

            return Booking::query()
                ->where('customer_id', $customer->id)
                ->pluck('company_id')
                ->filter()
                ->unique()
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();
        }

        return $user->company_id ? [(int) $user->company_id] : [];
    }

    /**
     * Returns null for global visibility and an explicit id list for scoped users.
     *
     * @return array<int, int>|null
     */
    private function visibleProjectIds(?User $user): ?array
    {
        if (! $user) {
            return [];
        }

        $companyScope = app(CompanyScopeService::class);

        if (config('builder360.single_company.enabled', true) && ! $this->isExternalDashboardUser($user)) {
            $companyId = $companyScope->companyIdFor($user);

            if ($companyId === null || $companyId <= 0) {
                return [];
            }

            return Project::query()
                ->where('company_id', $companyId)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        if ($companyScope->hasGlobalScope($user)) {
            return null;
        }

        if ($this->isPartnerPortalUser($user)) {
            $partnerIds = $this->partnerIdsForUser($user);

            if ($partnerIds === []) {
                return [];
            }

            $projectIds = collect()
                ->merge(Lead::query()->whereIn('partner_id', $partnerIds)->pluck('project_id'))
                ->merge(Booking::query()->whereIn('partner_id', $partnerIds)->pluck('project_id'))
                ->filter()
                ->unique()
                ->values()
                ->all();

            return array_map('intval', $projectIds);
        }

        if ($this->isBuyerPortalUser($user)) {
            $customer = $user->customer;

            if (! $customer) {
                return [];
            }

            return Booking::query()
                ->where('customer_id', $customer->id)
                ->pluck('project_id')
                ->filter()
                ->unique()
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();
        }

        if (! $user->company_id) {
            return [];
        }

        return Project::query()
            ->where('company_id', $user->company_id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    private function visibleProjectForUser(?User $user, ?int $projectId): ?Project
    {
        if (! $user || ! $projectId) {
            return null;
        }

        $projectIds = $this->visibleProjectIds($user);

        return Project::query()
            ->when(is_array($projectIds), fn (Builder $query): Builder => $query->whereIn('id', $projectIds ?: [0]))
            ->whereKey($projectId)
            ->first(['id', 'company_id', 'code', 'name', 'status']);
    }

    private function isPartnerPortalUser(?User $user): bool
    {
        return $user?->role?->scope_level === 'partner';
    }

    private function isBuyerPortalUser(?User $user): bool
    {
        return $user?->role?->scope_level === 'self' && $user->hasPermission('buyer.view');
    }

    /**
     * @return array<int, int>
     */
    private function partnerIdsForUser(?User $user): array
    {
        return app(PartnerScopeService::class)->activePartnerIdsForUser($user);
    }
}
