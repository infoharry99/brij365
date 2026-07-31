<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\AttendanceRecord;
use App\Models\AttendanceShift;
use App\Models\BoqItem;
use App\Models\EmployeeShiftAssignment;
use App\Models\Booking;
use App\Models\BookingPaymentSchedule;
use App\Models\CalendarEvent;
use App\Models\CollaborationMessage;
use App\Models\Company;
use App\Models\CollectionReceipt;
use App\Models\CommissionRule;
use App\Models\CommonAreaHandoverItem;
use App\Models\ComplianceObligation;
use App\Models\ConstructionMilestone;
use App\Models\ContractorMeasurement;
use App\Models\Customer;
use App\Models\DailyProgressReport;
use App\Models\DocumentCategory;
use App\Models\Employee;
use App\Models\EmployeeAsset;
use App\Models\EmployeeConfirmationCase;
use App\Models\EmployeeExitInterview;
use App\Models\EmployeeLoan;
use App\Models\EmployeeLeaveBalance;
use App\Models\ErpModule;
use App\Models\ExpenseClaim;
use App\Models\FinancialVoucher;
use App\Models\GstEntry;
use App\Models\HrHelpdeskTicket;
use App\Models\Candidate;
use App\Models\Interview;
use App\Models\JobOffer;
use App\Models\JobOpening;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadQualification;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\MaintenanceDue;
use App\Models\MaintenanceWorkOrder;
use App\Models\ManagedDocument;
use App\Models\MarketingCampaign;
use App\Models\Partner;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\PayrollComponent;
use App\Models\PaymentRequest;
use App\Models\GoodsReceipt;
use App\Models\HandoverSnag;
use App\Models\PossessionHandover;
use App\Models\ProspectInquiry;
use App\Models\ProjectApproval;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\SalaryAssignment;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureComponent;
use App\Models\Project;
use App\Models\ProjectUnit;
use App\Models\Role;
use App\Models\ReraRegistration;
use App\Models\ServiceTicket;
use App\Models\SiteVisit;
use App\Models\SocietyFormation;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\SystemSetting;
use App\Models\UnitPriceVersion;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vendor;
use App\Models\WorkTask;
use App\Services\Collaboration\ChatAccessService;
use App\Domain\Scoring\Support\LogicCenterPermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roles = collect([
            ['slug' => 'director', 'name' => 'Director', 'scope_level' => 'global', 'permissions' => ['*']],
            ['slug' => 'sales_head', 'name' => 'Sales Head', 'scope_level' => 'department', 'permissions' => ['crm.view', 'crm.manage', 'inventory.view', 'booking.view', 'booking.manage', 'collections.view', 'collections.manage', 'documents.view', 'documents.manage', 'possession.view', 'possession.manage', 'after_sales.view', 'after_sales.manage', 'after_sales.approve', 'leave.view', 'leave.request', 'attendance.view', 'attendance.request', 'performance.view', 'performance.manage', 'reports.view', 'collaboration.view', 'collaboration.manage', 'scoring.view']],
            ['slug' => 'construction_head', 'name' => 'Construction Head', 'scope_level' => 'department', 'permissions' => ['construction.view', 'construction.manage', 'procurement.view', 'procurement.manage', 'possession.view', 'possession.manage', 'after_sales.view', 'after_sales.manage', 'leave.view', 'leave.request', 'attendance.view', 'attendance.request', 'performance.view', 'performance.manage', 'reports.view', 'collaboration.view', 'collaboration.manage', 'scoring.view']],
            ['slug' => 'finance_head', 'name' => 'Finance Head', 'scope_level' => 'department', 'permissions' => ['finance.view', 'finance.manage', 'finance.approve', 'booking.view', 'collections.view', 'collections.manage', 'collections.approve', 'documents.view', 'documents.approve', 'payroll.view', 'payroll.approve', 'claims.view', 'loans.view', 'procurement.view', 'procurement.approve', 'construction.view', 'construction.approve', 'possession.view', 'possession.approve', 'after_sales.view', 'reports.view', 'collaboration.view', 'collaboration.manage', 'scoring.view']],
            ['slug' => 'hr_manager', 'name' => 'HR Manager', 'scope_level' => 'department', 'permissions' => ['hr.view', 'hr.manage', 'assets.view', 'assets.manage', 'claims.view', 'claims.manage', 'claims.approve', 'loans.view', 'loans.manage', 'loans.approve', 'helpdesk.view', 'helpdesk.manage', 'payroll.view', 'leave.view', 'leave.manage', 'leave.approve', 'attendance.view', 'attendance.manage', 'attendance.approve', 'performance.view', 'performance.manage', 'performance.approve', 'documents.view', 'documents.manage', 'documents.approve', 'recruitment.view', 'recruitment.approve', 'collaboration.view', 'collaboration.manage', 'scoring.view']],
            ['slug' => 'buyer', 'name' => 'Buyer', 'scope_level' => 'self', 'permissions' => ['buyer.view']],
            ['slug' => 'employee', 'name' => 'Employee', 'scope_level' => 'self', 'permissions' => ['employee.self_service']],
            ['slug' => 'payroll', 'name' => 'Payroll Admin', 'scope_level' => 'department', 'permissions' => ['payroll.view', 'payroll.manage', 'collaboration.view', 'collaboration.manage']],
            ['slug' => 'recruiter', 'name' => 'Recruiter', 'scope_level' => 'department', 'permissions' => ['recruitment.view', 'recruitment.manage', 'collaboration.view', 'collaboration.manage', 'scoring.view']],
            ['slug' => 'auditor', 'name' => 'Auditor', 'scope_level' => 'readonly', 'permissions' => ['audit.view', 'documents.view', 'reports.view', 'users.view', 'roles.view', 'collaboration.view', 'scoring.view']],
            ['slug' => 'compliance', 'name' => 'Compliance Officer', 'scope_level' => 'department', 'permissions' => ['compliance.view', 'compliance.manage', 'legal.view', 'legal.manage', 'legal.approve', 'documents.view', 'documents.approve', 'collaboration.view', 'collaboration.manage', 'scoring.view']],
            ['slug' => 'system_admin', 'name' => 'System Administrator', 'scope_level' => 'global', 'permissions' => ['settings.view', 'settings.manage', 'settings.approve', 'audit.view', 'reports.view', 'documents.view', 'users.view', 'users.manage', 'roles.view', 'roles.manage', 'collaboration.view', 'collaboration.manage', 'scoring.view', 'scoring.manage', 'scoring.approve', 'scoring.override', 'scoring.recalculate']],
            ['slug' => 'channel_partner', 'name' => 'Channel Partner', 'scope_level' => 'partner', 'permissions' => ['partner.portal']],
            ['slug' => 'executive_partner_broker', 'name' => 'Executive Partner (Broker)', 'scope_level' => 'partner', 'permissions' => ['partner.portal']],
        ])->mapWithKeys(fn (array $role) => [
            $role['slug'] => Role::updateOrCreate(
                ['slug' => $role['slug']],
                $role + ['is_active' => true],
            ),
        ]);

        $logicCenterDefaults = [
            'hr_manager' => [
                LogicCenterPermissions::PERFORMANCE_MANAGE,
                LogicCenterPermissions::PERFORMANCE_APPROVE,
                LogicCenterPermissions::PERFORMANCE_OVERRIDE_REQUEST,
                LogicCenterPermissions::ROSTER_MANAGE,
                LogicCenterPermissions::ROSTER_PUBLISH,
                LogicCenterPermissions::ROSTER_REOPEN,
                LogicCenterPermissions::SWAP_APPROVE,
                LogicCenterPermissions::ATTENDANCE_FINALIZE,
                LogicCenterPermissions::ATTENDANCE_REOPEN,
                LogicCenterPermissions::AUDIT_VIEW,
            ],
            'payroll' => [LogicCenterPermissions::STATUTORY_SIMULATE, LogicCenterPermissions::AUDIT_VIEW],
            'compliance' => [LogicCenterPermissions::STATUTORY_MANAGE, LogicCenterPermissions::STATUTORY_VERIFY, LogicCenterPermissions::STATUTORY_APPROVE, LogicCenterPermissions::AUDIT_VIEW],
            'auditor' => [LogicCenterPermissions::AUDIT_VIEW],
            'employee' => [LogicCenterPermissions::SWAP_REQUEST],
        ];

        foreach ($logicCenterDefaults as $roleSlug => $permissions) {
            $role = $roles->get($roleSlug);
            if (! $role instanceof Role) {
                continue;
            }

            $role->forceFill([
                'permissions' => array_values(array_unique(array_merge($role->permissions ?? [], $permissions))),
            ])->save();
        }

        $approvedShellRoutes = [
            'dashboard', 'approvals', 'notifications', 'reports',
            'tasks', 'calendar', 'chat', 'mailbox',
            'leads', 'qualification', 'sitevisits', 'sales', 'marketing', 'collections', 'funnel', 'performance',
            'projects', 'inventory', 'pricing', 'cost',
            'planning', 'progress', 'materials', 'procurement', 'vendors', 'contractors', 'boq',
            'hr', 'ess', 'payroll', 'recruitment',
            'finance', 'legal', 'documents', 'possession', 'complaints', 'maintenance',
            'buyer', 'inquiry',
            'admin', 'workflows', 'audit', 'settings', 'scoring',
            'partner',
        ];

        collect([
            ['group_name' => 'Overview', 'slug' => 'dashboard', 'name' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'grid'],
            ['group_name' => 'Overview', 'slug' => 'approvals', 'name' => 'Approvals', 'route' => 'approvals', 'icon' => 'check'],
            ['group_name' => 'Overview', 'slug' => 'notifications', 'name' => 'Notifications', 'route' => 'notifications', 'icon' => 'bell'],
            ['group_name' => 'Overview', 'slug' => 'reports', 'name' => 'Reports & Analytics', 'route' => 'reports', 'icon' => 'chart'],
            ['group_name' => 'Work Management', 'slug' => 'tasks', 'name' => 'Task Management', 'route' => 'tasks', 'icon' => 'tasks'],
            ['group_name' => 'Work Management', 'slug' => 'calendar', 'name' => 'Calendar Management', 'route' => 'calendar', 'icon' => 'calClock'],
            ['group_name' => 'Collaboration', 'slug' => 'chat', 'name' => 'Chat Connect', 'route' => 'chat', 'icon' => 'bubble'],
            ['group_name' => 'Collaboration', 'slug' => 'mailbox', 'name' => 'Mailbox', 'route' => 'mailbox', 'icon' => 'mail'],
            ['group_name' => 'Sales & CRM', 'slug' => 'leads', 'name' => 'Lead Management', 'route' => 'leads', 'icon' => 'users'],
            ['group_name' => 'Sales & CRM', 'slug' => 'qualification', 'name' => 'Lead Qualification', 'route' => 'qualification', 'icon' => 'filter'],
            ['group_name' => 'Sales & CRM', 'slug' => 'sitevisits', 'name' => 'Site Visits', 'route' => 'sitevisits', 'icon' => 'calendar'],
            ['group_name' => 'Sales & CRM', 'slug' => 'sales', 'name' => 'Sales & Booking', 'route' => 'sales', 'icon' => 'tag'],
            ['group_name' => 'Sales & CRM', 'slug' => 'marketing', 'name' => 'Marketing', 'route' => 'marketing', 'icon' => 'mega'],
            ['group_name' => 'Sales & CRM', 'slug' => 'collections', 'name' => 'Customer Collections', 'route' => 'collections', 'icon' => 'rupee'],
            ['group_name' => 'Sales & CRM', 'slug' => 'funnel', 'name' => 'Lead Funnel Analytics', 'route' => 'funnel', 'icon' => 'funnel'],
            ['group_name' => 'Sales & CRM', 'slug' => 'performance', 'name' => 'Performance Analytics', 'route' => 'performance', 'icon' => 'star'],
            ['group_name' => 'Projects & Inventory', 'slug' => 'projects', 'name' => 'Project Master', 'route' => 'projects', 'icon' => 'building'],
            ['group_name' => 'Projects & Inventory', 'slug' => 'inventory', 'name' => 'Unit Inventory', 'route' => 'inventory', 'icon' => 'layers'],
            ['group_name' => 'Projects & Inventory', 'slug' => 'pricing', 'name' => 'Pricing Intelligence', 'route' => 'pricing', 'icon' => 'spark'],
            ['group_name' => 'Projects & Inventory', 'slug' => 'cost', 'name' => 'Cost Control & ROI', 'route' => 'cost', 'icon' => 'trend'],
            ['group_name' => 'Construction', 'slug' => 'planning', 'name' => 'Construction Planning', 'route' => 'planning', 'icon' => 'calendar'],
            ['group_name' => 'Construction', 'slug' => 'progress', 'name' => 'Daily Site Progress', 'route' => 'progress', 'icon' => 'hardhat'],
            ['group_name' => 'Construction', 'slug' => 'materials', 'name' => 'Material & Store', 'route' => 'materials', 'icon' => 'box'],
            ['group_name' => 'Construction', 'slug' => 'stock-issues', 'name' => 'Stock Issue / Transfer', 'route' => 'stock-issues', 'icon' => 'shuffle'],
            ['group_name' => 'Construction', 'slug' => 'procurement', 'name' => 'Procurement', 'route' => 'procurement', 'icon' => 'cart'],
            ['group_name' => 'Construction', 'slug' => 'purchase-requisitions', 'name' => 'Purchase Requisitions', 'route' => 'purchase-requisitions', 'icon' => 'file'],
            ['group_name' => 'Construction', 'slug' => 'purchase-orders', 'name' => 'Purchase Orders', 'route' => 'purchase-orders', 'icon' => 'cart'],
            ['group_name' => 'Construction', 'slug' => 'goods-receipts', 'name' => 'Goods Receipts', 'route' => 'goods-receipts', 'icon' => 'truck'],
            ['group_name' => 'Construction', 'slug' => 'vendors', 'name' => 'Vendor Management', 'route' => 'vendors', 'icon' => 'truck'],
            ['group_name' => 'Construction', 'slug' => 'contractors', 'name' => 'Contractor Mgmt', 'route' => 'contractors', 'icon' => 'wrench'],
            ['group_name' => 'Construction', 'slug' => 'boq', 'name' => 'BOQ / Measurement', 'route' => 'boq', 'icon' => 'ruler'],
            ['group_name' => 'Construction', 'slug' => 'measurements', 'name' => 'Measurement Book', 'route' => 'measurements', 'icon' => 'ruler'],
            ['group_name' => 'Construction', 'slug' => 'contractor-bills', 'name' => 'Contractor Bills', 'route' => 'contractor-bills', 'icon' => 'receipt'],
            ['group_name' => 'People', 'slug' => 'hr', 'name' => 'HR & Employees', 'route' => 'hr', 'icon' => 'id'],
            ['group_name' => 'People', 'slug' => 'ess', 'name' => 'Employee Self-Service', 'route' => 'ess', 'icon' => 'user'],
            ['group_name' => 'People', 'slug' => 'hr-attendance', 'name' => 'Attendance & Shifts', 'route' => 'hr-attendance', 'icon' => 'clock'],
            ['group_name' => 'People', 'slug' => 'hr-leave', 'name' => 'Leave Management', 'route' => 'hr-leave', 'icon' => 'calendar'],
            ['group_name' => 'People', 'slug' => 'hr-performance', 'name' => 'Performance Reviews', 'route' => 'hr-performance', 'icon' => 'star'],
            ['group_name' => 'People', 'slug' => 'hr-confirmation', 'name' => 'Probation & Confirmation', 'route' => 'hr-confirmation', 'icon' => 'check'],
            ['group_name' => 'People', 'slug' => 'hr-separation', 'name' => 'Separation & F&F', 'route' => 'hr-separation', 'icon' => 'exit'],
            ['group_name' => 'People', 'slug' => 'hr-assets', 'name' => 'Employee Assets', 'route' => 'hr-assets', 'icon' => 'box'],
            ['group_name' => 'People', 'slug' => 'hr-claims', 'name' => 'Claims & Reimbursements', 'route' => 'hr-claims', 'icon' => 'receipt'],
            ['group_name' => 'People', 'slug' => 'hr-loans', 'name' => 'Employee Loans', 'route' => 'hr-loans', 'icon' => 'wallet'],
            ['group_name' => 'People', 'slug' => 'hr-helpdesk', 'name' => 'HR Helpdesk', 'route' => 'hr-helpdesk', 'icon' => 'headset'],
            ['group_name' => 'People', 'slug' => 'hr-documents', 'name' => 'Employee Documents', 'route' => 'hr-documents', 'icon' => 'folder'],
            ['group_name' => 'People', 'slug' => 'hr-compliance', 'name' => 'HR Compliance Rules', 'route' => 'hr-compliance', 'icon' => 'shield'],
            ['group_name' => 'People', 'slug' => 'payroll', 'name' => 'Payroll Admin', 'route' => 'payroll', 'icon' => 'wallet'],
            ['group_name' => 'People', 'slug' => 'payroll-structures', 'name' => 'Salary Structures', 'route' => 'payroll-structures', 'icon' => 'layers'],
            ['group_name' => 'People', 'slug' => 'payroll-components', 'name' => 'Payroll Components', 'route' => 'payroll-components', 'icon' => 'sliders'],
            ['group_name' => 'People', 'slug' => 'payroll-bank-batches', 'name' => 'Bank Transfer Batches', 'route' => 'payroll-bank-batches', 'icon' => 'bank'],
            ['group_name' => 'People', 'slug' => 'payroll-commissions', 'name' => 'Commission Runs', 'route' => 'payroll-commissions', 'icon' => 'rupee'],
            ['group_name' => 'People', 'slug' => 'payroll-tax-documents', 'name' => 'Tax Documents / Form 16', 'route' => 'payroll-tax-documents', 'icon' => 'file'],
            ['group_name' => 'People', 'slug' => 'recruitment', 'name' => 'Recruitment', 'route' => 'recruitment', 'icon' => 'funnel'],
            ['group_name' => 'People', 'slug' => 'recruitment-candidates', 'name' => 'Candidate Master', 'route' => 'recruitment-candidates', 'icon' => 'users'],
            ['group_name' => 'People', 'slug' => 'recruitment-interviews', 'name' => 'Interview Scheduler', 'route' => 'recruitment-interviews', 'icon' => 'calendar'],
            ['group_name' => 'People', 'slug' => 'recruitment-offers', 'name' => 'Offer Letters', 'route' => 'recruitment-offers', 'icon' => 'file'],
            ['group_name' => 'People', 'slug' => 'recruitment-sources', 'name' => 'Recruitment Sources', 'route' => 'recruitment-sources', 'icon' => 'chart'],
            ['group_name' => 'Operations', 'slug' => 'finance', 'name' => 'Accounts & Finance', 'route' => 'finance', 'icon' => 'wallet'],
            ['group_name' => 'Operations', 'slug' => 'finance-vouchers', 'name' => 'Vouchers', 'route' => 'finance-vouchers', 'icon' => 'receipt'],
            ['group_name' => 'Operations', 'slug' => 'finance-payment-requests', 'name' => 'Payment Requests', 'route' => 'finance-payment-requests', 'icon' => 'link'],
            ['group_name' => 'Operations', 'slug' => 'finance-gst-entries', 'name' => 'GST Entries', 'route' => 'finance-gst-entries', 'icon' => 'file'],
            ['group_name' => 'Operations', 'slug' => 'finance-gst-returns', 'name' => 'GST Return Periods', 'route' => 'finance-gst-returns', 'icon' => 'calendar'],
            ['group_name' => 'Operations', 'slug' => 'legal', 'name' => 'Legal / RERA', 'route' => 'legal', 'icon' => 'shield'],
            ['group_name' => 'Operations', 'slug' => 'legal-project-approvals', 'name' => 'Project Approvals', 'route' => 'legal-project-approvals', 'icon' => 'check'],
            ['group_name' => 'Operations', 'slug' => 'legal-obligations', 'name' => 'Compliance Obligations', 'route' => 'legal-obligations', 'icon' => 'calendar'],
            ['group_name' => 'Operations', 'slug' => 'documents', 'name' => 'Document Mgmt', 'route' => 'documents', 'icon' => 'folder'],
            ['group_name' => 'Operations', 'slug' => 'document-categories', 'name' => 'Document Categories', 'route' => 'document-categories', 'icon' => 'folder'],
            ['group_name' => 'Operations', 'slug' => 'possession', 'name' => 'Possession & Handover', 'route' => 'possession', 'icon' => 'key'],
            ['group_name' => 'Operations', 'slug' => 'possession-snags', 'name' => 'Snag List', 'route' => 'possession-snags', 'icon' => 'alert'],
            ['group_name' => 'Operations', 'slug' => 'after-sales', 'name' => 'After-Sales', 'route' => 'complaints', 'icon' => 'headset'],
            ['group_name' => 'Operations', 'slug' => 'after-sales-work-orders', 'name' => 'Service Work Orders', 'route' => 'after-sales-work-orders', 'icon' => 'wrench'],
            ['group_name' => 'Operations', 'slug' => 'maintenance', 'name' => 'Maintenance & Society', 'route' => 'maintenance', 'icon' => 'wrench'],
            ['group_name' => 'Operations', 'slug' => 'maintenance-handover-items', 'name' => 'Common Area Handover', 'route' => 'maintenance-handover-items', 'icon' => 'check'],
            ['group_name' => 'Operations', 'slug' => 'maintenance-dues', 'name' => 'Maintenance Dues', 'route' => 'maintenance-dues', 'icon' => 'rupee'],
            ['group_name' => 'Customer', 'slug' => 'buyer', 'name' => 'Buyer Portal', 'route' => 'buyer', 'icon' => 'home'],
            ['group_name' => 'Customer', 'slug' => 'inquiry', 'name' => 'Prospect Inquiry', 'route' => 'inquiry', 'icon' => 'globe'],
            ['group_name' => 'Customer', 'slug' => 'mobile', 'name' => 'Mobile Apps', 'route' => 'mobile', 'icon' => 'phone'],
            ['group_name' => 'System', 'slug' => 'administration', 'name' => 'Administration', 'route' => 'admin', 'icon' => 'sliders'],
            ['group_name' => 'System', 'slug' => 'admin-users', 'name' => 'User Management', 'route' => 'admin-users', 'icon' => 'users'],
            ['group_name' => 'System', 'slug' => 'admin-roles', 'name' => 'Role Management', 'route' => 'admin-roles', 'icon' => 'key'],
            ['group_name' => 'System', 'slug' => 'workflows', 'name' => 'Business Workflows', 'route' => 'workflows', 'icon' => 'funnel'],
            ['group_name' => 'System', 'slug' => 'audit', 'name' => 'Audit Trail', 'route' => 'audit', 'icon' => 'eye'],
            ['group_name' => 'System', 'slug' => 'auth', 'name' => 'Authentication', 'route' => 'auth', 'icon' => 'shield'],
            ['group_name' => 'System', 'slug' => 'settings', 'name' => 'System Settings', 'route' => 'settings', 'icon' => 'gear'],
            ['group_name' => 'System', 'slug' => 'scoring', 'name' => 'Scoring Logic', 'route' => 'scoring', 'icon' => 'sliders', 'required_permissions' => LogicCenterPermissions::navigation()],
            ['group_name' => 'System', 'slug' => 'data-imports', 'name' => 'Data Imports', 'route' => 'data-imports', 'icon' => 'upload'],
            ['group_name' => 'Partner Portal', 'slug' => 'partner', 'name' => 'Partner Dashboard', 'route' => 'partner', 'icon' => 'grid'],
        ])->each(fn (array $module, int $index) => ErpModule::updateOrCreate(
            ['slug' => $module['slug']],
            $module + ['sort_order' => ($index + 1) * 10, 'is_active' => in_array($module['route'], $approvedShellRoutes, true)],
        ));

        $companies = collect([
            ['code' => 'B360D', 'name' => 'Builder360 Developers Pvt Ltd', 'legal_name' => 'Builder360 Developers Private Limited', 'state' => 'MH'],
            ['code' => 'B360P', 'name' => 'Builder360 Projects LLP', 'legal_name' => 'Builder360 Projects LLP', 'state' => 'GJ'],
            ['code' => 'B360F', 'name' => 'Builder360 Facilities Pvt Ltd', 'legal_name' => 'Builder360 Facilities Private Limited', 'state' => 'RJ'],
        ])->mapWithKeys(fn (array $company) => [
            $company['code'] => Company::updateOrCreate(
                ['code' => $company['code']],
                $company + ['status' => $company['code'] === (string) config('builder360.single_company.code') ? 'active' : 'inactive'],
            ),
        ]);

        $branches = collect([
            ['company_code' => 'B360D', 'code' => 'PNQ-HO', 'name' => 'Pune Head Office', 'city' => 'Pune', 'state' => 'MH', 'type' => 'head_office'],
            ['company_code' => 'B360D', 'code' => 'MUM-SALES', 'name' => 'Mumbai Sales Office', 'city' => 'Mumbai', 'state' => 'MH', 'type' => 'sales_office'],
            ['company_code' => 'B360P', 'code' => 'AMD-BR', 'name' => 'Ahmedabad Branch', 'city' => 'Ahmedabad', 'state' => 'GJ', 'type' => 'branch'],
            ['company_code' => 'B360F', 'code' => 'JAI-BR', 'name' => 'Jaipur Branch', 'city' => 'Jaipur', 'state' => 'RJ', 'type' => 'branch'],
        ])->mapWithKeys(function (array $branch) use ($companies) {
            $company = $companies[$branch['company_code']];
            unset($branch['company_code']);

            return [
                $branch['code'] => Branch::updateOrCreate(
                    ['company_id' => $company->id, 'code' => $branch['code']],
                    $branch + ['company_id' => $company->id, 'status' => 'active'],
                ),
            ];
        });

        $projects = collect([
            ['company_code' => 'B360D', 'branch_code' => 'PNQ-HO', 'code' => 'SKY-PUN', 'name' => 'Skyline Residency', 'project_type' => 'residential', 'city' => 'Pune', 'state' => 'MH', 'budget_amount' => 3100000000, 'target_roi_percent' => 22.4],
            ['company_code' => 'B360D', 'branch_code' => 'PNQ-HO', 'code' => 'GRN-PUN', 'name' => 'Greenwood Heights', 'project_type' => 'residential', 'city' => 'Pune', 'state' => 'MH', 'budget_amount' => 2450000000, 'target_roi_percent' => 17.8],
            ['company_code' => 'B360D', 'branch_code' => 'MUM-SALES', 'code' => 'ORC-MUM', 'name' => 'Orchid Business Park', 'project_type' => 'commercial', 'city' => 'Navi Mumbai', 'state' => 'MH', 'budget_amount' => 4100000000, 'target_roi_percent' => 14.2],
            ['company_code' => 'B360F', 'branch_code' => 'JAI-BR', 'code' => 'LKV-PUN', 'name' => 'Lakeview Villas', 'project_type' => 'villa', 'city' => 'Pune', 'state' => 'RJ', 'budget_amount' => 1220000000, 'target_roi_percent' => 26.1],
            ['company_code' => 'B360P', 'branch_code' => 'AMD-BR', 'code' => 'MTO-PUN', 'name' => 'Metro One Towers', 'project_type' => 'mixed_use', 'city' => 'Pune', 'state' => 'GJ', 'budget_amount' => 5200000000, 'target_roi_percent' => 29.6],
        ])->mapWithKeys(function (array $project) use ($companies, $branches) {
            $company = $companies[$project['company_code']];
            $branch = $branches[$project['branch_code']];
            unset($project['company_code'], $project['branch_code']);

            return [
                $project['code'] => Project::updateOrCreate(
                    ['code' => $project['code']],
                    $project + ['company_id' => $company->id, 'branch_id' => $branch->id, 'status' => 'active'],
                ),
            ];
        });

        $documentCategories = collect([
            ['code' => 'RERA_CERT', 'name' => 'RERA Certificate', 'owner_type' => 'project', 'expiry_required' => true, 'reminder_days_before_expiry' => 60, 'retention_years' => 10],
            ['code' => 'BOOKING_FORM', 'name' => 'Booking Form', 'owner_type' => 'booking', 'expiry_required' => false, 'reminder_days_before_expiry' => 30, 'retention_years' => 8],
            ['code' => 'CUSTOMER_KYC', 'name' => 'Customer KYC', 'owner_type' => 'customer', 'expiry_required' => true, 'reminder_days_before_expiry' => 45, 'retention_years' => 8],
            ['code' => 'EMPLOYEE_KYC', 'name' => 'Employee KYC', 'owner_type' => 'employee', 'expiry_required' => true, 'reminder_days_before_expiry' => 45, 'retention_years' => 8],
        ])->mapWithKeys(fn (array $category) => [
            $category['code'] => DocumentCategory::updateOrCreate(
                ['company_id' => $companies['B360D']->id, 'code' => $category['code']],
                $category + ['company_id' => $companies['B360D']->id, 'is_active' => true],
            ),
        ]);

        $leaveTypes = collect([
            [
                'code' => 'EL',
                'name' => 'Earned Leave',
                'annual_entitlement_days' => 18,
                'is_paid' => true,
                'requires_document' => false,
                'allows_half_day' => true,
                'allow_negative_balance' => false,
                'carry_forward_enabled' => true,
                'max_carry_forward_days' => 30,
                'encashment_enabled' => true,
                'approval_chain' => ['reporting_manager', 'hr_manager'],
            ],
            [
                'code' => 'SL',
                'name' => 'Sick Leave',
                'annual_entitlement_days' => 7,
                'is_paid' => true,
                'requires_document' => true,
                'allows_half_day' => true,
                'allow_negative_balance' => false,
                'carry_forward_enabled' => false,
                'max_carry_forward_days' => 0,
                'encashment_enabled' => false,
                'approval_chain' => ['reporting_manager', 'hr_manager'],
            ],
            [
                'code' => 'LOP',
                'name' => 'Loss of Pay',
                'annual_entitlement_days' => 0,
                'is_paid' => false,
                'requires_document' => false,
                'allows_half_day' => true,
                'allow_negative_balance' => true,
                'carry_forward_enabled' => false,
                'max_carry_forward_days' => 0,
                'encashment_enabled' => false,
                'approval_chain' => ['hr_manager'],
            ],
        ])->mapWithKeys(fn (array $leaveType) => [
            $leaveType['code'] => LeaveType::updateOrCreate(
                ['company_id' => $companies['B360D']->id, 'code' => $leaveType['code']],
                $leaveType + ['company_id' => $companies['B360D']->id, 'is_active' => true],
            ),
        ]);

        $attendanceShift = AttendanceShift::updateOrCreate(
            ['company_id' => $companies['B360D']->id, 'code' => 'GEN'],
            [
                'name' => 'General Shift',
                'starts_at' => '09:30:00',
                'ends_at' => '18:30:00',
                'is_overnight' => false,
                'late_grace_minutes' => 10,
                'early_leave_grace_minutes' => 10,
                'half_day_threshold_minutes' => 240,
                'full_day_threshold_minutes' => 480,
                'rules' => ['rounding' => 'nearest_minute', 'weekend_policy' => 'configuration_pending'],
                'is_active' => true,
            ],
        );

        $payrollComponents = collect([
            ['code' => 'BASIC', 'name' => 'Basic Salary', 'component_type' => 'earning', 'calculation_type' => 'percentage_of_ctc', 'is_taxable' => true, 'is_statutory' => false, 'rules' => ['percentage_of_ctc' => 50]],
            ['code' => 'HRA', 'name' => 'House Rent Allowance', 'component_type' => 'earning', 'calculation_type' => 'percentage_of_ctc', 'is_taxable' => true, 'is_statutory' => false, 'rules' => ['percentage_of_ctc' => 25]],
            ['code' => 'CONV', 'name' => 'Conveyance Allowance', 'component_type' => 'earning', 'calculation_type' => 'percentage_of_ctc', 'is_taxable' => true, 'is_statutory' => false, 'rules' => ['percentage_of_ctc' => 15]],
            ['code' => 'PF', 'name' => 'Provident Fund', 'component_type' => 'deduction', 'calculation_type' => 'percentage_of_ctc', 'is_taxable' => false, 'is_statutory' => true, 'rules' => ['percentage_of_ctc' => 10]],
        ])->mapWithKeys(fn (array $component) => [
            $component['code'] => PayrollComponent::updateOrCreate(
                ['company_id' => $companies['B360D']->id, 'code' => $component['code']],
                $component + ['company_id' => $companies['B360D']->id, 'is_active' => true],
            ),
        ]);

        $defaultSalaryStructure = SalaryStructure::updateOrCreate(
            ['company_id' => $companies['B360D']->id, 'code' => 'B360-M1', 'version' => 1],
            [
                'name' => 'Builder360 Manager Grade Structure',
                'effective_from' => now()->startOfYear()->toDateString(),
                'effective_to' => null,
                'monthly_ctc' => 100000,
                'status' => 'active',
                'metadata' => ['source' => 'database_seed'],
            ],
        );

        collect([
            ['component' => 'BASIC', 'amount' => 50000, 'percentage_of_ctc' => 50, 'sort_order' => 10],
            ['component' => 'HRA', 'amount' => 25000, 'percentage_of_ctc' => 25, 'sort_order' => 20],
            ['component' => 'CONV', 'amount' => 15000, 'percentage_of_ctc' => 15, 'sort_order' => 30],
            ['component' => 'PF', 'amount' => 10000, 'percentage_of_ctc' => 10, 'sort_order' => 40],
        ])->each(fn (array $component) => SalaryStructureComponent::updateOrCreate(
            [
                'salary_structure_id' => $defaultSalaryStructure->id,
                'payroll_component_id' => $payrollComponents[$component['component']]->id,
            ],
            [
                'amount' => $component['amount'],
                'percentage_of_ctc' => $component['percentage_of_ctc'],
                'sort_order' => $component['sort_order'],
            ],
        ));

        $seedUserPassword = $this->seedUserPassword();

        $users = collect([
            ['role' => 'director', 'company' => 'B360D', 'name' => 'Aditya Mehra', 'email' => 'aditya.mehra@builder360.test'],
            ['role' => 'sales_head', 'company' => 'B360D', 'name' => 'Priya Nair', 'email' => 'priya.nair@builder360.test'],
            ['role' => 'construction_head', 'company' => 'B360D', 'name' => 'Rajesh Kulkarni', 'email' => 'rajesh.kulkarni@builder360.test'],
            ['role' => 'finance_head', 'company' => 'B360D', 'name' => 'Suresh Iyer', 'email' => 'suresh.iyer@builder360.test'],
            ['role' => 'hr_manager', 'company' => 'B360D', 'name' => 'Deepa Rao', 'email' => 'deepa.rao@builder360.test'],
            ['role' => 'payroll', 'company' => 'B360D', 'name' => 'Kavita Shah', 'email' => 'kavita.shah@builder360.test'],
            ['role' => 'recruiter', 'company' => 'B360D', 'name' => 'Ananya Sen', 'email' => 'ananya.sen@builder360.test'],
            ['role' => 'compliance', 'company' => 'B360D', 'name' => 'Meera Kapoor', 'email' => 'meera.kapoor@builder360.test'],
            ['role' => 'auditor', 'company' => 'B360D', 'name' => 'Ishaan Trivedi', 'email' => 'ishaan.trivedi@builder360.test'],
            ['role' => 'system_admin', 'company' => 'B360D', 'name' => 'Nikhil Desai', 'email' => 'nikhil.desai@builder360.test'],
            ['role' => 'employee', 'company' => 'B360D', 'name' => 'Amit Verma', 'email' => 'amit.verma@builder360.test'],
            ['role' => 'channel_partner', 'company' => 'B360D', 'name' => 'Sameer Bafna', 'email' => 'sameer.bafna@partners.builder360.test'],
            ['role' => 'executive_partner_broker', 'company' => 'B360D', 'name' => 'Farhan Shaikh', 'email' => 'farhan.shaikh@partners.builder360.test'],
            ['role' => 'buyer', 'company' => 'B360D', 'name' => 'Rohan Shah', 'email' => 'rohan.shah@example.test'],
        ])->mapWithKeys(fn (array $user) => [
            $user['email'] => User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'role_id' => $roles[$user['role']]->id,
                    'company_id' => $companies[$user['company']]->id,
                    'name' => $user['name'],
                    'password' => Hash::make($seedUserPassword),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ],
            ),
        ]);

        collect([
            [
                'setting_key' => 'hr.attendance.rules',
                'setting_group' => 'hr',
                'label' => 'HR Attendance Rules',
                'description' => 'Configurable attendance grace, threshold and rounding rules.',
                'value_type' => 'object',
                'value' => [
                    'late_grace_minutes' => 10,
                    'early_leave_grace_minutes' => 10,
                    'half_day_threshold_minutes' => 240,
                    'full_day_threshold_minutes' => 480,
                    'rounding' => 'nearest_minute',
                ],
            ],
            [
                'setting_key' => 'after_sales.sla_hours',
                'setting_group' => 'after_sales',
                'label' => 'After-Sales SLA Hours',
                'description' => 'Priority-wise service ticket SLA configuration.',
                'value_type' => 'object',
                'value' => [
                    'low' => 72,
                    'medium' => 48,
                    'high' => 24,
                    'critical' => 8,
                ],
            ],
            [
                'setting_key' => 'workflow.approval_chains',
                'setting_group' => 'workflow',
                'label' => 'Approval Chain Catalogue',
                'description' => 'Configurable approval chains for finance, HR, procurement, legal and possession workflows.',
                'value_type' => 'object',
                'value' => [
                    'collections' => ['collector', 'finance_approver'],
                    'leave' => ['reporting_manager', 'hr_manager'],
                    'procurement' => ['requester', 'finance_approver'],
                    'legal' => ['compliance_officer', 'director'],
                    'possession' => ['sales_or_construction', 'finance_approver'],
                ],
            ],
            [
                'setting_key' => 'collaboration.task_settings',
                'setting_group' => 'collaboration',
                'label' => 'Collaboration Task Settings',
                'description' => 'Configurable task workflow, notification and archival controls used by Task Management.',
                'value_type' => 'object',
                'value' => [
                    'auto_progress' => true,
                    'require_completion_approval' => true,
                    'lock_completed' => false,
                    'transfer_requires_approval' => true,
                    'auto_archive_days' => 30,
                    'notifications' => [
                        'assignment' => true,
                        'comments_mentions' => true,
                        'due_soon' => true,
                        'overdue' => true,
                    ],
                    'status_map' => [
                        'open' => 'todo',
                        'in_progress' => 'inprogress',
                        'blocked' => 'blocked',
                        'completed' => 'done',
                        'cancelled' => 'cancelled',
                    ],
                ],
            ],
            [
                'setting_key' => 'collaboration.mailbox_settings',
                'setting_group' => 'collaboration',
                'label' => 'Collaboration Mailbox Settings',
                'description' => 'Mailbox account details, internal message controls, CRM linking preferences and notification settings.',
                'value_type' => 'object',
                'value' => [
                    'internal_messages_enabled' => true,
                    'external_sync_enabled' => false,
                    'allowed_providers' => ['internal_builder360', 'google_oauth_metadata', 'imap_smtp_metadata'],
                    'accounts' => [
                        [
                            'id' => 'acc-internal-builder360',
                            'provider' => 'internal_builder360',
                            'email' => 'internal.mailbox@builder360.test',
                            'name' => 'Builder360 Internal Mailbox',
                            'authType' => 'Internal mailbox',
                            'color' => '#2570eb',
                            'isDefault' => true,
                            'syncStatus' => 'active',
                            'lastSync' => 'Internal message records',
                            'signature' => 'Builder360 Internal Mailbox',
                        ],
                        [
                            'id' => 'acc-google-metadata-demo',
                            'provider' => 'google_oauth_metadata',
                            'email' => 'sales@builder360.example',
                            'name' => 'Sales Mailbox Metadata',
                            'authType' => 'Google Workspace metadata only',
                            'color' => '#dc2f3a',
                            'isDefault' => false,
                            'syncStatus' => 'metadata_only',
                            'lastSync' => 'not connected',
                            'signature' => 'Sales Desk · Builder360',
                        ],
                    ],
                    'sync_scope' => [
                        'inbox' => true,
                        'sent' => true,
                        'archived' => true,
                        'trash' => false,
                        'spam' => false,
                        'historical' => false,
                        'frequency' => 'manual',
                    ],
                    'crm_linking' => [
                        'auto_match' => true,
                        'auto_create_contacts' => false,
                        'domain_link' => true,
                        'deal_link' => true,
                        'ignore_newsletters' => true,
                        'ignore_no_reply' => true,
                        'review_queue' => true,
                    ],
                    'notifications' => [
                        'new_email' => true,
                        'failed_sync' => true,
                        'failed_send' => true,
                        'in_app' => true,
                        'desktop' => false,
                    ],
                    'prototype_notice' => 'External Gmail, IMAP and SMTP providers require approved connection setup before live use.',
                ],
            ],
            [
                'setting_key' => ChatAccessService::SETTING_KEY,
                'setting_group' => 'collaboration',
                'label' => 'Chat Connect Access',
                'description' => 'Role-based Chat Connect access and communication capability controls.',
                'value_type' => 'object',
                'value' => [
                    'roles' => app(ChatAccessService::class)->defaultRoleAccess(),
                ],
            ],
            [
                'setting_key' => 'governance.backup_dr',
                'setting_group' => 'governance',
                'label' => 'Backup and Disaster Recovery Metadata',
                'description' => 'Administrative backup/DR planning metadata; operational backup jobs remain deployment-specific.',
                'value_type' => 'object',
                'value' => [
                    'schedule' => 'daily',
                    'retention_days' => 30,
                    'offsite_copy' => true,
                    'rpo_hours' => 24,
                    'rto_hours' => 8,
                    'owner' => 'System Administrator',
                ],
            ],
            [
                'setting_key' => 'payroll.tax_rules',
                'setting_group' => 'payroll',
                'label' => 'Payroll Tax and Form 16 Rules',
                'description' => 'Verified Form 16 generation controls, payroll-year lock and statutory tax-document metadata.',
                'value_type' => 'object',
                'value' => [
                    'financial_year' => '2026-2027',
                    'payroll_year_locked' => true,
                    'verified' => true,
                    'verified_by' => 'Compliance Officer',
                    'verified_on' => now()->toDateString(),
                    'form16_template_version' => 'FY2026-2027-v1',
                    'standard_deduction' => 50000,
                    'tds_component_codes' => ['TDS', 'INCOME_TAX'],
                    'prototype_notice' => 'Client statutory expert must confirm final tax rules before use.',
                ],
            ],
            [
                'setting_key' => 'hr.leave.rules',
                'setting_group' => 'hr',
                'label' => 'HR Leave Processing and Encashment Rules',
                'description' => 'Configurable leave accrual, carry-forward, lapse and encashment defaults.',
                'value_type' => 'object',
                'value' => [
                    'monthly_accrual_enabled' => true,
                    'year_end_processing_enabled' => true,
                    'encashment_tax_rate' => 0.10,
                    'encashment_formula' => 'approved_days * monthly_ctc / 30 - configured_tax',
                    'payroll_inclusion_status' => 'payroll_marked',
                    'approval_chain' => ['employee', 'hr_approver', 'payroll_processor'],
                ],
            ],
            [
                'setting_key' => 'payroll.commission_rules',
                'setting_group' => 'payroll',
                'label' => 'Sales Commission Processing Rules',
                'description' => 'Configurable commission basis, approval chain and payroll inclusion controls.',
                'value_type' => 'object',
                'value' => [
                    'supported_rule_types' => ['fixed', 'percentage', 'slab', 'target'],
                    'supported_basis' => ['booking_value', 'collection_received'],
                    'payroll_inclusion_status' => 'approved',
                    'approval_chain' => ['payroll_processor', 'finance_approver'],
                    'segregation_of_duties' => true,
                    'prototype_notice' => 'Payout rules must be approved by client finance and HR owners.',
                ],
            ],
            [
                'setting_key' => 'finance.gst_rules',
                'setting_group' => 'finance',
                'label' => 'GST Register and Return Controls',
                'description' => 'Configurable GST transaction types, return-period approval chain and filing readiness controls.',
                'value_type' => 'object',
                'value' => [
                    'supported_transaction_types' => ['output', 'input', 'reverse_charge', 'adjustment'],
                    'default_tax_rates' => [0, 5, 12, 18, 28],
                    'return_frequency' => 'monthly',
                    'approval_chain' => ['finance_preparer', 'finance_or_compliance_approver', 'period_lock'],
                    'locked_period_blocks_new_entries' => true,
                    'prototype_notice' => 'GST figures must be confirmed by a client-appointed tax professional before statutory filing.',
                ],
            ],
            [
                'setting_key' => 'construction.contractor_billing',
                'setting_group' => 'construction',
                'label' => 'Contractor Billing Rules',
                'description' => 'Configurable contractor bill retention, deduction and approval controls.',
                'value_type' => 'object',
                'value' => [
                    'default_retention_percent' => 5,
                    'max_retention_percent' => 10,
                    'max_deduction_percent_of_gross' => 30,
                    'approval_chain' => ['construction_preparer', 'finance_or_construction_approver', 'finance_payment_marker'],
                    'segregation_of_duties' => true,
                    'payment_statuses' => ['submitted', 'approved', 'partially_paid', 'paid'],
                ],
            ],
        ])->each(function (array $setting) use ($companies, $users): void {
            SystemSetting::updateOrCreate(
                [
                    'scope_key' => 'company:'.$companies['B360D']->id,
                    'setting_key' => $setting['setting_key'],
                    'version' => 1,
                ],
                $setting + [
                    'company_id' => $companies['B360D']->id,
                    'created_by_user_id' => $users['aditya.mehra@builder360.test']->id,
                    'approved_by_user_id' => $users['nikhil.desai@builder360.test']->id,
                    'scope_key' => 'company:'.$companies['B360D']->id,
                    'status' => 'active',
                    'version' => 1,
                    'effective_from' => now()->startOfYear()->toDateString(),
                    'approved_at' => now(),
                    'workflow_history' => [
                        ['status' => 'draft', 'actor' => 'Aditya Mehra', 'note' => 'Initial settings baseline', 'at' => now()->subHour()->toISOString()],
                        ['status' => 'active', 'actor' => 'Nikhil Desai', 'note' => 'Initial settings approved', 'at' => now()->toISOString()],
                    ],
                    'metadata' => ['source' => 'database_seed'],
                ],
            );
        });

        CommissionRule::updateOrCreate(
            ['company_id' => $companies['B360D']->id, 'rule_code' => 'COMM-SALES-BOOKING-1'],
            [
                'project_id' => $projects['SKY-PUN']->id,
                'created_by_user_id' => $users['kavita.shah@builder360.test']->id,
                'name' => 'Skyline Sales Booking Commission',
                'rule_type' => 'percentage',
                'basis' => 'booking_value',
                'rate_percent' => 1.2500,
                'fixed_amount' => 0,
                'target_amount' => 0,
                'slab_rules' => [
                    ['from' => 0, 'to' => 10000000, 'rate_percent' => 1.0],
                    ['from' => 10000000, 'to' => null, 'rate_percent' => 1.25],
                ],
                'eligibility_rules' => [
                    'booking_status' => 'confirmed',
                    'employee_source' => 'booking.booked_by_user.employee',
                    'payroll_inclusion' => 'approved_commission_run_same_period',
                ],
                'effective_from' => now()->startOfYear()->toDateString(),
                'effective_to' => null,
                'status' => 'active',
                'workflow_history' => [
                    ['status' => 'active', 'actor' => 'Kavita Shah', 'note' => 'Commission rule approved for initial processing', 'at' => now()->toISOString()],
                ],
                'metadata' => ['source' => 'database_seed'],
            ],
        );

        $jobOpening = JobOpening::updateOrCreate(
            ['opening_code' => 'JOB-1001'],
            [
                'company_id' => $companies['B360D']->id,
                'branch_id' => $branches['PNQ-HO']->id,
                'project_id' => $projects['SKY-PUN']->id,
                'created_by_user_id' => $users['deepa.rao@builder360.test']->id,
                'title' => 'Senior Sales Executive',
                'department' => 'Sales',
                'designation' => 'Senior Executive',
                'positions' => 3,
                'employment_type' => 'full_time',
                'work_location' => 'Pune',
                'budget_min_ctc' => 650000,
                'budget_max_ctc' => 900000,
                'status' => 'open',
                'target_hiring_date' => now()->addDays(45)->toDateString(),
                'required_skills' => ['CRM', 'Channel Sales', 'Site Visits', 'Negotiation'],
                'metadata' => ['source' => 'database_seed'],
            ],
        );

        $candidate = Candidate::updateOrCreate(
            ['candidate_code' => 'CAN-1001'],
            [
                'company_id' => $companies['B360D']->id,
                'job_opening_id' => $jobOpening->id,
                'owner_user_id' => $users['ananya.sen@builder360.test']->id,
                'name' => 'Meera Joshi',
                'email' => 'meera.joshi@example.test',
                'phone' => '+91 98111 21001',
                'source' => 'Naukri',
                'current_company' => 'Metro Homes',
                'experience_years' => 4.5,
                'current_ctc' => 620000,
                'expected_ctc' => 780000,
                'notice_period_days' => 30,
                'skills' => ['CRM', 'Real Estate Sales', 'Site Visits'],
                'documents' => [['type' => 'resume', 'name' => 'meera-joshi-resume.pdf']],
                'stage' => 'offer_released',
                'status' => 'active',
                'stage_history' => [
                    ['stage' => 'screening', 'actor' => 'Ananya Sen', 'note' => 'Candidate screened', 'at' => now()->subDays(5)->toISOString()],
                    ['stage' => 'interview_scheduled', 'actor' => 'Ananya Sen', 'note' => 'Interview scheduled', 'at' => now()->subDays(4)->toISOString()],
                    ['stage' => 'offer_released', 'actor' => 'Deepa Rao', 'note' => 'Offer released', 'at' => now()->subDay()->toISOString()],
                ],
                'notes' => 'Recruitment candidate with released offer.',
            ],
        );

        Interview::updateOrCreate(
            ['interview_code' => 'INT-1001'],
            [
                'company_id' => $companies['B360D']->id,
                'candidate_id' => $candidate->id,
                'scheduled_by_user_id' => $users['ananya.sen@builder360.test']->id,
                'round_name' => 'HR + Sales Round',
                'scheduled_at' => now()->addDays(3)->setTime(11, 0)->toDateTimeString(),
                'duration_minutes' => 60,
                'mode' => 'video',
                'venue_or_link' => 'https://meet.example.test/builder360-demo',
                'panel_user_ids' => [$users['deepa.rao@builder360.test']->id, $users['priya.nair@builder360.test']->id],
                'status' => 'scheduled',
                'feedback' => [],
            ],
        );

        JobOffer::updateOrCreate(
            ['offer_number' => 'OFF-1001'],
            [
                'company_id' => $companies['B360D']->id,
                'candidate_id' => $candidate->id,
                'created_by_user_id' => $users['ananya.sen@builder360.test']->id,
                'released_by_user_id' => $users['deepa.rao@builder360.test']->id,
                'template_code' => 'SALES_EXECUTIVE_APPOINTMENT',
                'offered_ctc' => 780000,
                'joining_date' => now()->addDays(30)->toDateString(),
                'placeholders' => [
                    'candidate_name' => 'Meera Joshi',
                    'designation' => 'Senior Executive',
                    'department' => 'Sales',
                    'joining_date' => now()->addDays(30)->toDateString(),
                    'offered_ctc' => 780000,
                ],
                'status' => 'released',
                'released_at' => now()->subDay(),
                'document_history' => [
                    ['event' => 'offer_draft_created', 'actor' => 'Ananya Sen', 'at' => now()->subDays(2)->toISOString()],
                    ['event' => 'offer_released', 'actor' => 'Deepa Rao', 'at' => now()->subDay()->toISOString()],
                ],
            ],
        );

        $vendor = Vendor::updateOrCreate(
            ['company_id' => $companies['B360D']->id, 'vendor_code' => 'VEN-1001'],
            [
                'name' => 'BuildMat Supplies Pvt Ltd',
                'vendor_type' => 'material',
                'contact_name' => 'Nitin Agarwal',
                'email' => 'accounts@buildmat.example.test',
                'phone' => '+91 98222 30001',
                'gstin' => '27AABCB1234F1Z5',
                'pan' => 'AABCB1234F',
                'address' => [
                    'line1' => 'Plot 24, Industrial Estate',
                    'city' => 'Pune',
                    'state' => 'MH',
                    'pincode' => '411045',
                ],
                'bank_details' => [
                    'account_holder' => 'BuildMat Supplies Pvt Ltd',
                    'account_masked' => 'XXXXXX4582',
                    'ifsc' => 'HDFC0000123',
                ],
                'compliance_documents' => [
                    ['type' => 'gst_certificate', 'status' => 'verified', 'expires_on' => now()->addYear()->toDateString()],
                    ['type' => 'pan', 'status' => 'verified'],
                ],
                'status' => 'active',
                'metadata' => ['source' => 'database_seed'],
            ],
        );

        $contractorVendor = Vendor::updateOrCreate(
            ['company_id' => $companies['B360D']->id, 'vendor_code' => 'CON-1001'],
            [
                'name' => 'Precision Civil Contractors',
                'vendor_type' => 'contractor',
                'contact_name' => 'Mahesh Patil',
                'email' => 'billing@precisioncivil.example.test',
                'phone' => '+91 98222 30009',
                'gstin' => '27AABCP9876H1Z7',
                'pan' => 'AABCP9876H',
                'address' => [
                    'line1' => 'Survey 18, Civil Lines',
                    'city' => 'Pune',
                    'state' => 'MH',
                    'pincode' => '411041',
                ],
                'bank_details' => [
                    'account_holder' => 'Precision Civil Contractors',
                    'account_masked' => 'XXXXXX6721',
                    'ifsc' => 'ICIC0000456',
                ],
                'compliance_documents' => [
                    ['type' => 'work_order', 'status' => 'verified', 'expires_on' => now()->addMonths(10)->toDateString()],
                    ['type' => 'labour_license', 'status' => 'verified', 'expires_on' => now()->addMonths(8)->toDateString()],
                ],
                'status' => 'active',
                'metadata' => ['source' => 'database_seed'],
            ],
        );

        $purchaseRequisition = PurchaseRequisition::updateOrCreate(
            ['requisition_number' => 'PR-1001'],
            [
                'company_id' => $companies['B360D']->id,
                'project_id' => $projects['SKY-PUN']->id,
                'requested_by_user_id' => $users['rajesh.kulkarni@builder360.test']->id,
                'approved_by_user_id' => $users['suresh.iyer@builder360.test']->id,
                'department' => 'Construction',
                'required_by' => now()->addDays(10)->toDateString(),
                'priority' => 'high',
                'status' => 'approved',
                'items' => [
                    [
                        'item_code' => 'CEMENT-OPC-53',
                        'description' => 'OPC 53 Grade Cement',
                        'unit' => 'bag',
                        'quantity' => 500,
                        'estimated_rate' => 385,
                        'estimated_amount' => 192500,
                    ],
                    [
                        'item_code' => 'STEEL-TMT-12',
                        'description' => 'TMT Steel 12mm',
                        'unit' => 'kg',
                        'quantity' => 2500,
                        'estimated_rate' => 64,
                        'estimated_amount' => 160000,
                    ],
                ],
                'estimated_total' => 352500,
                'purpose' => 'Material requirement for Skyline Residency slab work.',
                'workflow_history' => [
                    ['status' => 'submitted', 'actor' => 'Rajesh Kulkarni', 'note' => 'Purchase requisition submitted', 'at' => now()->subDays(4)->toISOString()],
                    ['status' => 'approved', 'actor' => 'Suresh Iyer', 'note' => 'Approval completed', 'at' => now()->subDays(3)->toISOString()],
                ],
                'approved_at' => now()->subDays(3),
            ],
        );

        $purchaseOrder = PurchaseOrder::updateOrCreate(
            ['po_number' => 'PO-1001'],
            [
                'company_id' => $companies['B360D']->id,
                'project_id' => $projects['SKY-PUN']->id,
                'purchase_requisition_id' => $purchaseRequisition->id,
                'vendor_id' => $vendor->id,
                'created_by_user_id' => $users['rajesh.kulkarni@builder360.test']->id,
                'approved_by_user_id' => $users['suresh.iyer@builder360.test']->id,
                'po_date' => now()->subDays(2)->toDateString(),
                'expected_delivery_on' => now()->addDays(5)->toDateString(),
                'status' => 'partially_received',
                'payment_terms' => '30 days from GRN acceptance',
                'items' => [
                    [
                        'item_code' => 'CEMENT-OPC-53',
                        'description' => 'OPC 53 Grade Cement',
                        'unit' => 'bag',
                        'quantity' => 500,
                        'rate' => 380,
                        'tax_rate' => 18,
                        'line_amount' => 190000,
                        'tax_amount' => 34200,
                        'total_amount' => 224200,
                    ],
                    [
                        'item_code' => 'STEEL-TMT-12',
                        'description' => 'TMT Steel 12mm',
                        'unit' => 'kg',
                        'quantity' => 2500,
                        'rate' => 63,
                        'tax_rate' => 18,
                        'line_amount' => 157500,
                        'tax_amount' => 28350,
                        'total_amount' => 185850,
                    ],
                ],
                'subtotal' => 347500,
                'tax_amount' => 62550,
                'total_amount' => 410050,
                'terms' => 'Material subject to site quality inspection and weighment.',
                'workflow_history' => [
                    ['status' => 'draft', 'actor' => 'Rajesh Kulkarni', 'note' => 'Purchase order draft created', 'at' => now()->subDays(2)->toISOString()],
                    ['status' => 'approved', 'actor' => 'Suresh Iyer', 'note' => 'Finance approval completed', 'at' => now()->subDay()->toISOString()],
                ],
                'approved_at' => now()->subDay(),
            ],
        );

        $goodsReceipt = GoodsReceipt::updateOrCreate(
            ['grn_number' => 'GRN-1001'],
            [
                'company_id' => $companies['B360D']->id,
                'project_id' => $projects['SKY-PUN']->id,
                'purchase_order_id' => $purchaseOrder->id,
                'received_by_user_id' => $users['rajesh.kulkarni@builder360.test']->id,
                'received_on' => now()->toDateString(),
                'delivery_challan_number' => 'DC-BMS-1001',
                'status' => 'received',
                'items' => [
                    [
                        'item_code' => 'CEMENT-OPC-53',
                        'description' => 'OPC 53 Grade Cement',
                        'unit' => 'bag',
                        'ordered_quantity' => 500,
                        'previous_accepted_quantity' => 0,
                        'accepted_quantity' => 300,
                        'rejected_quantity' => 0,
                        'rate' => 380,
                        'accepted_amount' => 114000,
                        'remarks' => 'Accepted after site inspection.',
                    ],
                ],
                'accepted_total' => 114000,
                'quality_notes' => 'First lot accepted. Balance material pending.',
                'metadata' => ['source' => 'database_seed'],
            ],
        );

        $seedStockItem = StockItem::updateOrCreate(
            [
                'company_id' => $companies['B360D']->id,
                'project_id' => $projects['SKY-PUN']->id,
                'store_type' => 'site',
                'item_code' => 'CEMENT-OPC-53',
            ],
            [
                'description' => 'OPC 53 Grade Cement',
                'unit' => 'bag',
                'on_hand_quantity' => 300,
                'stock_value' => 114000,
                'average_rate' => 380,
                'minimum_stock_quantity' => 50,
                'status' => 'active',
                'last_movement_at' => now(),
                'metadata' => ['source' => 'database_seed', 'grn_number' => 'GRN-1001'],
            ],
        );

        StockMovement::updateOrCreate(
            [
                'source_type' => 'goods_receipt',
                'source_id' => $goodsReceipt->id,
                'item_code' => 'CEMENT-OPC-53',
                'movement_type' => 'inward',
            ],
            [
                'company_id' => $companies['B360D']->id,
                'project_id' => $projects['SKY-PUN']->id,
                'stock_item_id' => $seedStockItem->id,
                'purchase_order_id' => $purchaseOrder->id,
                'goods_receipt_id' => $goodsReceipt->id,
                'created_by_user_id' => $users['rajesh.kulkarni@builder360.test']->id,
                'movement_number' => 'STM-1001',
                'movement_date' => $goodsReceipt->received_on,
                'store_type' => 'site',
                'description' => 'OPC 53 Grade Cement',
                'unit' => 'bag',
                'quantity' => 300,
                'rate' => 380,
                'amount' => 114000,
                'balance_after_quantity' => 300,
                'balance_after_value' => 114000,
                'metadata' => ['source' => 'database_seed', 'grn_number' => 'GRN-1001', 'po_number' => 'PO-1001'],
            ],
        );

        $foundationMilestone = ConstructionMilestone::updateOrCreate(
            ['project_id' => $projects['SKY-PUN']->id, 'milestone_code' => 'SKY-FDN'],
            [
                'company_id' => $companies['B360D']->id,
                'created_by_user_id' => $users['rajesh.kulkarni@builder360.test']->id,
                'name' => 'Foundation Work',
                'phase' => 'Foundation',
                'planned_start_on' => now()->subMonths(2)->toDateString(),
                'planned_end_on' => now()->subMonth()->toDateString(),
                'actual_start_on' => now()->subMonths(2)->addDays(2)->toDateString(),
                'actual_end_on' => now()->subMonth()->addDays(3)->toDateString(),
                'weight_percent' => 18,
                'progress_percent' => 100,
                'status' => 'completed',
                'dependencies' => [],
                'metadata' => [
                    'source' => 'database_seed',
                    'last_progress_report_number' => 'DPR-1001',
                    'last_progress_report_date' => now()->subDay()->toDateString(),
                ],
            ],
        );

        $slabMilestone = ConstructionMilestone::updateOrCreate(
            ['project_id' => $projects['SKY-PUN']->id, 'milestone_code' => 'SKY-SLAB-03'],
            [
                'company_id' => $companies['B360D']->id,
                'created_by_user_id' => $users['rajesh.kulkarni@builder360.test']->id,
                'name' => 'Third Slab Casting',
                'phase' => 'Structure',
                'planned_start_on' => now()->subDays(10)->toDateString(),
                'planned_end_on' => now()->addDays(12)->toDateString(),
                'actual_start_on' => now()->subDays(8)->toDateString(),
                'actual_end_on' => null,
                'weight_percent' => 12,
                'progress_percent' => 45,
                'status' => 'in_progress',
                'dependencies' => ['SKY-FDN'],
                'metadata' => [
                    'source' => 'database_seed',
                    'last_progress_report_number' => 'DPR-1001',
                    'last_progress_report_date' => now()->subDay()->toDateString(),
                ],
            ],
        );

        $slabBoq = BoqItem::updateOrCreate(
            ['project_id' => $projects['SKY-PUN']->id, 'boq_code' => 'BOQ-SKY-RCC-001'],
            [
                'company_id' => $companies['B360D']->id,
                'construction_milestone_id' => $slabMilestone->id,
                'vendor_id' => $contractorVendor->id,
                'created_by_user_id' => $users['rajesh.kulkarni@builder360.test']->id,
                'trade' => 'RCC',
                'description' => 'RCC slab casting including shuttering, reinforcement fixing and concrete pour',
                'unit' => 'sqm',
                'planned_quantity' => 1000,
                'rate' => 1250,
                'budget_amount' => 1250000,
                'measured_quantity' => 200,
                'certified_quantity' => 200,
                'certified_amount' => 250000,
                'status' => 'active',
                'specifications' => [
                    'mix_grade' => 'M30',
                    'measurement_basis' => 'certified slab area',
                    'quality_check' => 'cube test and QA checklist required',
                ],
                'metadata' => [
                    'source' => 'database_seed',
                    'last_measurement_number' => 'MB-10001',
                    'last_measurement_date' => now()->subDays(2)->toDateString(),
                ],
            ],
        );

        ContractorMeasurement::updateOrCreate(
            ['measurement_number' => 'MB-10001'],
            [
                'company_id' => $companies['B360D']->id,
                'project_id' => $projects['SKY-PUN']->id,
                'vendor_id' => $contractorVendor->id,
                'submitted_by_user_id' => $users['rajesh.kulkarni@builder360.test']->id,
                'approved_by_user_id' => $users['suresh.iyer@builder360.test']->id,
                'measurement_date' => now()->subDays(2)->toDateString(),
                'bill_reference' => 'PC/RA/001',
                'status' => 'approved',
                'measured_total' => 250000,
                'certified_total' => 250000,
                'lines' => [
                    [
                        'boq_item_id' => $slabBoq->id,
                        'boq_code' => 'BOQ-SKY-RCC-001',
                        'description' => 'RCC slab casting including shuttering, reinforcement fixing and concrete pour',
                        'trade' => 'RCC',
                        'unit' => 'sqm',
                        'rate' => 1250,
                        'planned_quantity' => 1000,
                        'previous_certified_quantity' => 0,
                        'measured_quantity' => 200,
                        'certified_quantity' => 200,
                        'measured_amount' => 250000,
                        'certified_amount' => 250000,
                        'remarks' => 'First certified RA measurement.',
                    ],
                ],
                'remarks' => 'Approved measurement certificate for RCC slab work.',
                'workflow_history' => [
                    ['status' => 'submitted', 'actor' => 'Rajesh Kulkarni', 'note' => 'Contractor measurement submitted', 'at' => now()->subDays(2)->toISOString()],
                    ['status' => 'approved', 'actor' => 'Suresh Iyer', 'note' => 'Certification approval completed', 'at' => now()->subDay()->toISOString()],
                ],
                'approved_at' => now()->subDay(),
            ],
        );

        DailyProgressReport::updateOrCreate(
            ['report_number' => 'DPR-1001'],
            [
                'company_id' => $companies['B360D']->id,
                'project_id' => $projects['SKY-PUN']->id,
                'prepared_by_user_id' => $users['rajesh.kulkarni@builder360.test']->id,
                'approved_by_user_id' => $users['suresh.iyer@builder360.test']->id,
                'report_date' => now()->subDay()->toDateString(),
                'weather' => 'Clear',
                'manpower_count' => 86,
                'manpower_breakup' => [
                    ['category' => 'Mason', 'count' => 24],
                    ['category' => 'Helper', 'count' => 42],
                    ['category' => 'Bar bender', 'count' => 12],
                    ['category' => 'Supervisor', 'count' => 8],
                ],
                'progress_items' => [
                    [
                        'milestone_id' => $foundationMilestone->id,
                        'milestone_code' => 'SKY-FDN',
                        'milestone_name' => 'Foundation Work',
                        'work_done' => 'Foundation closure documentation completed.',
                        'progress_percent' => 100,
                    ],
                    [
                        'milestone_id' => $slabMilestone->id,
                        'milestone_code' => 'SKY-SLAB-03',
                        'milestone_name' => 'Third Slab Casting',
                        'work_done' => 'Rebar binding and shuttering progressed for third slab.',
                        'progress_percent' => 45,
                    ],
                ],
                'materials_used' => [
                    ['item_code' => 'CEMENT-OPC-53', 'description' => 'OPC 53 Grade Cement', 'unit' => 'bag', 'quantity' => 120],
                    ['item_code' => 'STEEL-TMT-12', 'description' => 'TMT Steel 12mm', 'unit' => 'kg', 'quantity' => 640],
                ],
                'equipment_used' => [
                    ['name' => 'Concrete mixer', 'hours' => 5.5],
                    ['name' => 'Tower crane', 'hours' => 4],
                ],
                'work_summary' => 'Third slab preparation progressed as planned with no major interruption.',
                'safety_observations' => 'Toolbox talk completed; no incident reported.',
                'quality_observations' => 'Rebar spacing checked by site QA engineer.',
                'blockers' => null,
                'status' => 'approved',
                'workflow_history' => [
                    ['status' => 'submitted', 'actor' => 'Rajesh Kulkarni', 'note' => 'Daily progress report submitted', 'at' => now()->subDay()->toISOString()],
                    ['status' => 'approved', 'actor' => 'Suresh Iyer', 'note' => 'Approval completed', 'at' => now()->toISOString()],
                ],
                'approved_at' => now(),
            ],
        );

        ReraRegistration::updateOrCreate(
            ['company_id' => $companies['B360D']->id, 'registration_number' => 'P52100012345'],
            [
                'project_id' => $projects['SKY-PUN']->id,
                'created_by_user_id' => $users['meera.kapoor@builder360.test']->id,
                'verified_by_user_id' => $users['aditya.mehra@builder360.test']->id,
                'authority_name' => 'MahaRERA',
                'state_code' => 'MH',
                'registered_on' => now()->subMonths(8)->toDateString(),
                'expires_on' => now()->addMonths(16)->toDateString(),
                'status' => 'verified',
                'document_reference' => 'RERA-DEMO-SKY',
                'conditions' => ['Quarterly project updates must be filed on time.', 'Advertising must include RERA registration number.'],
                'workflow_history' => [
                    ['status' => 'submitted', 'actor' => 'Meera Kapoor', 'note' => 'RERA registration submitted', 'at' => now()->subMonths(8)->toISOString()],
                    ['status' => 'verified', 'actor' => 'Aditya Mehra', 'note' => 'Verification completed', 'at' => now()->subMonths(8)->addDay()->toISOString()],
                ],
                'metadata' => ['source' => 'database_seed'],
                'verified_at' => now()->subMonths(8)->addDay(),
            ],
        );

        ProjectApproval::updateOrCreate(
            ['project_id' => $projects['SKY-PUN']->id, 'approval_code' => 'CC-SKY-001'],
            [
                'company_id' => $companies['B360D']->id,
                'responsible_user_id' => $users['meera.kapoor@builder360.test']->id,
                'verified_by_user_id' => $users['aditya.mehra@builder360.test']->id,
                'approval_type' => 'Commencement Certificate',
                'authority_name' => 'Pune Municipal Corporation',
                'application_number' => 'PMC/CC/2026/1001',
                'applied_on' => now()->subMonths(10)->toDateString(),
                'approved_on' => now()->subMonths(9)->toDateString(),
                'expires_on' => now()->addMonths(24)->toDateString(),
                'status' => 'verified',
                'required_for' => 'Construction start',
                'document_reference' => 'PMC-CC-SKY-001',
                'conditions' => ['Construction must follow sanctioned plan.', 'Safety norms must be maintained at site.'],
                'workflow_history' => [
                    ['status' => 'approved', 'actor' => 'Meera Kapoor', 'note' => 'Municipal approval completed', 'at' => now()->subMonths(9)->toISOString()],
                    ['status' => 'verified', 'actor' => 'Aditya Mehra', 'note' => 'Verification completed', 'at' => now()->subMonths(9)->addDay()->toISOString()],
                ],
                'metadata' => ['source' => 'database_seed'],
                'verified_at' => now()->subMonths(9)->addDay(),
            ],
        );

        ComplianceObligation::updateOrCreate(
            ['obligation_number' => 'COMP-1001'],
            [
                'company_id' => $companies['B360D']->id,
                'project_id' => $projects['SKY-PUN']->id,
                'assigned_to_user_id' => $users['meera.kapoor@builder360.test']->id,
                'completed_by_user_id' => null,
                'title' => 'MahaRERA quarterly project update',
                'compliance_type' => 'RERA Quarterly Filing',
                'due_on' => now()->addDays(20)->toDateString(),
                'frequency' => 'quarterly',
                'priority' => 'high',
                'status' => 'open',
                'evidence_document_reference' => null,
                'notes' => 'Prepare construction progress, booking, collection and approval update before filing.',
                'workflow_history' => [
                    ['status' => 'open', 'actor' => 'Meera Kapoor', 'note' => 'Compliance obligation opened', 'at' => now()->toISOString()],
                ],
                'metadata' => ['source' => 'database_seed'],
                'completed_at' => null,
            ],
        );

        collect([
            ['code' => 'CP-001', 'name' => 'Bafna Realty Network', 'partner_type' => 'channel_partner', 'contact_name' => 'Sameer Bafna', 'email' => 'sameer.bafna@partners.builder360.test', 'phone' => '+91 98200 10001'],
            ['code' => 'BR-001', 'name' => 'Shaikh Executive Brokers', 'partner_type' => 'executive_partner_broker', 'contact_name' => 'Farhan Shaikh', 'email' => 'farhan.shaikh@partners.builder360.test', 'phone' => '+91 98200 10002'],
        ])->each(fn (array $partner) => Partner::updateOrCreate(
            ['code' => $partner['code']],
            $partner + [
                'status' => 'active',
                'commission_rules' => ['type' => 'percentage', 'rate' => 1.5, 'basis' => 'approved_collections'],
            ],
        ));

        $partner = Partner::where('code', 'CP-001')->firstOrFail();

        $customers = collect([
            ['code' => 'CUS-1001', 'name' => 'Rohan Shah', 'email' => 'rohan.shah@example.test', 'phone' => '+91 98111 10001', 'source' => 'Channel walk-in', 'portal_user' => 'rohan.shah@example.test'],
            ['code' => 'CUS-1002', 'name' => 'Neha Patil', 'email' => 'neha.patil@example.test', 'phone' => '+91 98111 10002', 'source' => 'Referral'],
            ['code' => 'CUS-1003', 'name' => 'Arvind Jain', 'email' => 'arvind.jain@example.test', 'phone' => '+91 98111 10003', 'source' => 'Broker network'],
        ])->mapWithKeys(function (array $customer) use ($users) {
            $portalUserId = isset($customer['portal_user']) ? $users[$customer['portal_user']]->id : null;
            unset($customer['portal_user']);

            return [
                $customer['code'] => Customer::updateOrCreate(
                    ['code' => $customer['code']],
                    $customer + ['status' => 'active', 'portal_user_id' => $portalUserId],
                ),
            ];
        });

        $campaigns = collect([
            [
                'campaign_code' => 'MC-10001',
                'project' => 'SKY-PUN',
                'name' => 'Skyline Channel Partner Launch',
                'channel' => 'channel_partner',
                'source' => 'Channel walk-in',
                'status' => 'active',
                'budget_amount' => 350000,
                'target_leads' => 25,
                'target_bookings' => 4,
                'utm_source' => 'channel_partner',
                'utm_medium' => 'partner',
                'utm_campaign' => 'skyline_launch',
                'audience_segment' => 'Pune premium residential buyers',
            ],
            [
                'campaign_code' => 'MC-10002',
                'project' => 'GRN-PUN',
                'name' => 'Greenwood Referral Drive',
                'channel' => 'referral',
                'source' => 'Referral',
                'status' => 'active',
                'budget_amount' => 180000,
                'target_leads' => 15,
                'target_bookings' => 2,
                'utm_source' => 'referral',
                'utm_medium' => 'offline',
                'utm_campaign' => 'greenwood_referrals',
                'audience_segment' => 'Existing buyer referrals',
            ],
        ])->mapWithKeys(function (array $campaign) use ($companies, $projects, $users) {
            $project = $projects[$campaign['project']];
            unset($campaign['project']);

            return [
                $campaign['campaign_code'] => MarketingCampaign::updateOrCreate(
                    ['campaign_code' => $campaign['campaign_code']],
                    $campaign + [
                        'company_id' => $companies['B360D']->id,
                        'project_id' => $project->id,
                        'created_by_user_id' => $users['priya.nair@builder360.test']->id,
                        'approved_by_user_id' => $users['priya.nair@builder360.test']->id,
                        'start_on' => now()->subDays(30)->toDateString(),
                        'end_on' => now()->addDays(45)->toDateString(),
                        'workflow_history' => [
                            ['status' => 'active', 'actor_user_id' => $users['priya.nair@builder360.test']->id, 'actor' => 'Priya Nair', 'note' => 'Marketing campaign activated', 'at' => now()->subDays(30)->toISOString()],
                        ],
                        'metadata' => ['source' => 'database_seed'],
                        'approved_at' => now()->subDays(30),
                    ],
                ),
            ];
        });

        collect([
            ['lead_code' => 'LD-1001', 'customer' => 'CUS-1001', 'project' => 'SKY-PUN', 'campaign' => 'MC-10001', 'source' => 'Channel walk-in', 'stage' => 'Qualified', 'expected_value' => 12500000],
            ['lead_code' => 'LD-1002', 'customer' => 'CUS-1002', 'project' => 'GRN-PUN', 'campaign' => 'MC-10002', 'source' => 'Referral', 'stage' => 'Site Visit Planned', 'expected_value' => 9200000],
            ['lead_code' => 'LD-1003', 'customer' => 'CUS-1003', 'project' => 'MTO-PUN', 'campaign' => null, 'source' => 'Broker network', 'stage' => 'Negotiation', 'expected_value' => 17600000],
        ])->each(function (array $lead) use ($companies, $projects, $customers, $partner, $users, $campaigns) {
            $project = $projects[$lead['project']];

            $createdLead = Lead::updateOrCreate(
                ['lead_code' => $lead['lead_code']],
                [
                    'company_id' => $companies['B360D']->id,
                    'project_id' => $project->id,
                    'customer_id' => $customers[$lead['customer']]->id,
                    'partner_id' => $partner->id,
                    'marketing_campaign_id' => $lead['campaign'] ? $campaigns[$lead['campaign']]->id : null,
                    'owner_user_id' => $users['priya.nair@builder360.test']->id,
                    'source' => $lead['source'],
                    'stage' => $lead['stage'],
                    'status' => 'open',
                    'budget_min' => $lead['expected_value'] * 0.85,
                    'budget_max' => $lead['expected_value'] * 1.15,
                    'expected_value' => $lead['expected_value'],
                    'follow_up_at' => now()->addDays(2),
                ],
            );

            LeadActivity::updateOrCreate(
                ['activity_number' => 'LA-SEED-'.$lead['lead_code']],
                [
                    'company_id' => $createdLead->company_id,
                    'project_id' => $createdLead->project_id,
                    'lead_id' => $createdLead->id,
                    'actor_user_id' => $users['priya.nair@builder360.test']->id,
                    'marketing_campaign_id' => $createdLead->marketing_campaign_id,
                    'activity_type' => $createdLead->marketing_campaign_id ? 'campaign_response' : 'created',
                    'activity_at' => now()->subDays(5),
                    'subject' => $createdLead->marketing_campaign_id ? 'Seeded campaign response captured' : 'Seeded lead created',
                    'description' => 'CRM activity history for '.$createdLead->lead_code.'.',
                    'new_stage' => $createdLead->stage,
                    'outcome' => $createdLead->status,
                    'next_follow_up_at' => $createdLead->follow_up_at,
                    'metadata' => ['source' => 'database_seed'],
                ],
            );
        });

        collect([
            [
                'inquiry_number' => 'PI-10001',
                'project' => 'SKY-PUN',
                'name' => 'Sneha Deshmukh',
                'email' => 'sneha.deshmukh@example.test',
                'phone' => '+91 98222 10001',
                'source' => 'Website',
                'channel' => 'website',
                'status' => ProspectInquiry::STATUS_NEW,
                'budget_min' => 9000000,
                'budget_max' => 11500000,
                'message' => 'Interested in 2BHK units with December possession.',
            ],
            [
                'inquiry_number' => 'PI-10002',
                'project' => 'GRN-PUN',
                'name' => 'Vikram Joshi',
                'email' => 'vikram.joshi@example.test',
                'phone' => '+91 98222 10002',
                'source' => 'Landing Page',
                'channel' => 'landing_page',
                'status' => ProspectInquiry::STATUS_ASSIGNED,
                'budget_min' => 7500000,
                'budget_max' => 9800000,
                'message' => 'Requested weekend site visit and home-loan support.',
            ],
        ])->each(function (array $inquiry) use ($projects, $users): void {
            $project = $projects[$inquiry['project']];

            ProspectInquiry::updateOrCreate(
                ['inquiry_number' => $inquiry['inquiry_number']],
                [
                    'company_id' => $project->company_id,
                    'project_id' => $project->id,
                    'assigned_to_user_id' => $inquiry['status'] === ProspectInquiry::STATUS_ASSIGNED
                        ? $users['priya.nair@builder360.test']->id
                        : null,
                    'name' => $inquiry['name'],
                    'email' => $inquiry['email'],
                    'phone' => $inquiry['phone'],
                    'source' => $inquiry['source'],
                    'channel' => $inquiry['channel'],
                    'preferred_contact_method' => 'phone',
                    'status' => $inquiry['status'],
                    'budget_min' => $inquiry['budget_min'],
                    'budget_max' => $inquiry['budget_max'],
                    'message' => $inquiry['message'],
                    'consent_to_contact' => true,
                    'utm_source' => strtolower(str_replace(' ', '_', $inquiry['source'])),
                    'utm_medium' => 'seed',
                    'utm_campaign' => 'builder360_demo',
                    'assigned_at' => $inquiry['status'] === ProspectInquiry::STATUS_ASSIGNED ? now()->subDay() : null,
                    'metadata' => [
                        'source' => 'database_seed',
                        'capture_context' => ['channel' => $inquiry['channel']],
                    ],
                ],
            );
        });

        $leadForQualification = Lead::where('lead_code', 'LD-1002')->firstOrFail();

        LeadQualification::updateOrCreate(
            ['qualification_number' => 'LQ-10001'],
            [
                'company_id' => $leadForQualification->company_id,
                'lead_id' => $leadForQualification->id,
                'qualified_by_user_id' => $users['priya.nair@builder360.test']->id,
                'status' => 'qualified',
                'score' => 82,
                'budget_score' => 20,
                'authority_score' => 20,
                'need_score' => 22,
                'timeline_score' => 20,
                'preferred_configuration' => '2BHK',
                'verified_budget_min' => 8000000,
                'verified_budget_max' => 9500000,
                'expected_booking_date' => now()->addDays(21)->toDateString(),
                'decision_notes' => 'Seeded qualified referral lead with clear budget, decision authority and near-term booking timeline.',
                'requirements' => [
                    'configuration' => '2BHK',
                    'preferred_floor' => '8-12',
                    'funding' => 'home_loan_preapproved',
                    'purpose' => 'self_use',
                ],
                'workflow_history' => [
                    ['status' => 'qualified', 'actor_user_id' => $users['priya.nair@builder360.test']->id, 'actor' => 'Priya Nair', 'note' => 'Lead qualification completed', 'at' => now()->subDay()->toISOString()],
                ],
                'metadata' => ['source' => 'database_seed'],
                'qualified_at' => now()->subDay(),
            ],
        );

        SiteVisit::updateOrCreate(
            ['visit_number' => 'SV-10001'],
            [
                'company_id' => $leadForQualification->company_id,
                'project_id' => $leadForQualification->project_id,
                'lead_id' => $leadForQualification->id,
                'customer_id' => $leadForQualification->customer_id,
                'scheduled_by_user_id' => $users['priya.nair@builder360.test']->id,
                'assigned_to_user_id' => $users['priya.nair@builder360.test']->id,
                'status' => 'scheduled',
                'scheduled_at' => now()->addDays(4)->setTime(15, 0),
                'duration_minutes' => 90,
                'visit_mode' => 'site',
                'meeting_location' => 'Greenwood Heights Experience Center',
                'meeting_url' => null,
                'agenda' => 'Show sample flat, explain payment plan and confirm booking readiness.',
                'outcome_notes' => null,
                'outcome' => null,
                'completed_at' => null,
                'cancelled_at' => null,
                'next_follow_up_at' => null,
                'attendees' => [
                    ['name' => 'Neha Patil', 'phone' => '+91 98111 10002', 'role' => 'Primary buyer'],
                ],
                'workflow_history' => [
                    ['status' => 'scheduled', 'actor_user_id' => $users['priya.nair@builder360.test']->id, 'actor' => 'Priya Nair', 'note' => 'Site visit scheduled', 'at' => now()->subHours(6)->toISOString()],
                ],
                'metadata' => ['source' => 'database_seed'],
            ],
        );

        $units = collect([
            [
                'project' => 'SKY-PUN',
                'unit_code' => 'SKY-A-1204',
                'tower' => 'A',
                'floor' => '12',
                'unit_number' => '1204',
                'unit_type' => '3BHK',
                'carpet_area_sqft' => 980,
                'saleable_area_sqft' => 1340,
                'base_rate' => 9300,
                'base_price' => 12462000,
                'floor_rise' => 240000,
                'parking_charges' => 450000,
                'other_charges' => 325000,
                'tax_amount' => 650000,
                'total_price' => 14127000,
                'status' => 'available',
            ],
            [
                'project' => 'SKY-PUN',
                'unit_code' => 'SKY-A-1205',
                'tower' => 'A',
                'floor' => '12',
                'unit_number' => '1205',
                'unit_type' => '3BHK',
                'carpet_area_sqft' => 965,
                'saleable_area_sqft' => 1325,
                'base_rate' => 9250,
                'base_price' => 12256250,
                'floor_rise' => 240000,
                'parking_charges' => 450000,
                'other_charges' => 315000,
                'tax_amount' => 640000,
                'total_price' => 13901250,
                'status' => 'available',
            ],
            [
                'project' => 'GRN-PUN',
                'unit_code' => 'GRN-B-0802',
                'tower' => 'B',
                'floor' => '08',
                'unit_number' => '0802',
                'unit_type' => '2BHK',
                'carpet_area_sqft' => 760,
                'saleable_area_sqft' => 1010,
                'base_rate' => 8100,
                'base_price' => 8181000,
                'floor_rise' => 110000,
                'parking_charges' => 350000,
                'other_charges' => 260000,
                'tax_amount' => 445000,
                'total_price' => 9346000,
                'status' => 'available',
            ],
            [
                'project' => 'MTO-PUN',
                'unit_code' => 'MTO-B-1803',
                'tower' => 'B',
                'floor' => '18',
                'unit_number' => '1803',
                'unit_type' => '4BHK',
                'carpet_area_sqft' => 1325,
                'saleable_area_sqft' => 1785,
                'base_rate' => 9800,
                'base_price' => 17493000,
                'floor_rise' => 360000,
                'parking_charges' => 650000,
                'other_charges' => 425000,
                'tax_amount' => 910000,
                'total_price' => 19838000,
                'status' => 'available',
            ],
        ])->mapWithKeys(function (array $unit) use ($projects) {
            $project = $projects[$unit['project']];
            unset($unit['project']);

            return [
                $unit['unit_code'] => ProjectUnit::updateOrCreate(
                    ['unit_code' => $unit['unit_code']],
                    $unit + [
                        'company_id' => $project->company_id,
                        'project_id' => $project->id,
                    ],
                ),
            ];
        });

        $unitPriceVersions = $units->mapWithKeys(function (ProjectUnit $unit) use ($users) {
            $grossBeforeTax = (float) $unit->base_price
                + (float) $unit->floor_rise
                + (float) $unit->parking_charges
                + (float) $unit->other_charges;

            return [
                $unit->unit_code => UnitPriceVersion::updateOrCreate(
                    ['price_code' => 'UPV-'.$unit->unit_code.'-V1'],
                    [
                        'company_id' => $unit->company_id,
                        'project_id' => $unit->project_id,
                        'project_unit_id' => $unit->id,
                        'created_by_user_id' => $users['priya.nair@builder360.test']->id,
                        'approved_by_user_id' => $users['suresh.iyer@builder360.test']->id,
                        'version_number' => 1,
                        'status' => 'active',
                        'effective_from' => now()->startOfYear()->toDateString(),
                        'effective_to' => null,
                        'base_rate' => $unit->base_rate,
                        'base_price' => $unit->base_price,
                        'floor_premium' => $unit->floor_rise,
                        'location_premium' => 0,
                        'parking_charges' => $unit->parking_charges,
                        'other_charges' => $unit->other_charges,
                        'tax_rate_percent' => $grossBeforeTax > 0 ? round(((float) $unit->tax_amount / $grossBeforeTax) * 100, 4) : 0,
                        'gross_price_before_tax' => $grossBeforeTax,
                        'tax_amount' => $unit->tax_amount,
                        'total_price' => $unit->total_price,
                        'charge_breakup' => [
                            'floor_premium' => $unit->floor_rise,
                            'parking_charges' => $unit->parking_charges,
                            'other_charges' => $unit->other_charges,
                        ],
                        'workflow_history' => [
                            ['status' => 'draft', 'actor_user_id' => $users['priya.nair@builder360.test']->id, 'actor' => 'Priya Nair', 'note' => 'Price version draft created', 'at' => now()->subDays(20)->toISOString()],
                            ['status' => 'active', 'actor_user_id' => $users['suresh.iyer@builder360.test']->id, 'actor' => 'Suresh Iyer', 'note' => 'Finance-approved unit price activated', 'at' => now()->subDays(19)->toISOString()],
                        ],
                        'metadata' => ['source' => 'database_seed'],
                        'approved_at' => now()->subDays(19),
                    ],
                ),
            ];
        });

        $bookedUnit = $units['SKY-A-1204'];
        $booking = Booking::updateOrCreate(
            ['booking_code' => 'BK-1001'],
            [
                'company_id' => $companies['B360D']->id,
                'project_id' => $projects['SKY-PUN']->id,
                'project_unit_id' => $bookedUnit->id,
                'unit_price_version_id' => $unitPriceVersions['SKY-A-1204']->id,
                'customer_id' => $customers['CUS-1001']->id,
                'lead_id' => Lead::where('lead_code', 'LD-1001')->firstOrFail()->id,
                'partner_id' => $partner->id,
                'booked_by_user_id' => $users['priya.nair@builder360.test']->id,
                'status' => 'confirmed',
                'booked_on' => now()->toDateString(),
                'agreement_value' => 14127000,
                'discount_amount' => 0,
                'tax_amount' => 650000,
                'net_receivable' => 14127000,
                'booking_amount' => 500000,
                'commercials' => [
                    'price_basis' => 'all_inclusive',
                    'inventory_rate' => 9300,
                    'approval_status' => 'confirmed_demo',
                ],
            ],
        );

        $bookedUnit->forceFill(['status' => 'booked', 'reserved_until' => null])->save();

        Lead::where('lead_code', 'LD-1001')->update([
            'stage' => 'Booked',
            'status' => 'won',
        ]);

        collect([
            ['sequence' => 1, 'milestone' => 'Booking Amount', 'percentage' => 10, 'amount' => 1412700, 'due_on' => now()->toDateString(), 'status' => 'partially_paid'],
            ['sequence' => 2, 'milestone' => 'Agreement', 'percentage' => 20, 'amount' => 2825400, 'due_on' => now()->addDays(15)->toDateString(), 'status' => 'pending'],
            ['sequence' => 3, 'milestone' => 'Slab Completion', 'percentage' => 40, 'amount' => 5650800, 'due_on' => now()->addMonths(6)->toDateString(), 'status' => 'pending'],
            ['sequence' => 4, 'milestone' => 'Possession', 'percentage' => 30, 'amount' => 4238100, 'due_on' => now()->addMonths(18)->toDateString(), 'status' => 'pending'],
        ])->each(fn (array $schedule) => BookingPaymentSchedule::updateOrCreate(
            ['booking_id' => $booking->id, 'sequence' => $schedule['sequence']],
            $schedule + ['booking_id' => $booking->id],
        ));

        $bookingAmountSchedule = BookingPaymentSchedule::where('booking_id', $booking->id)
            ->where('sequence', 1)
            ->firstOrFail();

        $agreementSchedule = BookingPaymentSchedule::where('booking_id', $booking->id)
            ->where('sequence', 2)
            ->firstOrFail();

        CollectionReceipt::updateOrCreate(
            ['receipt_number' => 'RCPT-1001'],
            [
                'company_id' => $booking->company_id,
                'project_id' => $booking->project_id,
                'booking_id' => $booking->id,
                'booking_payment_schedule_id' => $bookingAmountSchedule->id,
                'customer_id' => $booking->customer_id,
                'collected_by_user_id' => $users['priya.nair@builder360.test']->id,
                'approved_by_user_id' => $users['suresh.iyer@builder360.test']->id,
                'status' => 'approved',
                'receipt_date' => now()->toDateString(),
                'payment_mode' => 'neft',
                'instrument_number' => 'NEFT-DEMO-1001',
                'bank_name' => 'Demo Bank',
                'amount' => 500000,
                'tax_deducted_amount' => 0,
                'notes' => 'Approved booking amount receipt.',
                'metadata' => ['source' => 'database_seed', 'approval_reference' => 'DEMO-FIN-1001'],
                'approved_at' => now(),
            ],
        );

        PaymentRequest::updateOrCreate(
            ['request_number' => 'PAYREQ-10001'],
            [
                'company_id' => $booking->company_id,
                'project_id' => $booking->project_id,
                'booking_id' => $booking->id,
                'booking_payment_schedule_id' => $agreementSchedule->id,
                'customer_id' => $booking->customer_id,
                'created_by_user_id' => $users['suresh.iyer@builder360.test']->id,
                'paid_by_user_id' => null,
                'collection_receipt_id' => null,
                'gateway_provider' => 'prototype',
                'gateway_reference' => 'B360PAY-DEMO-10001',
                'status' => 'requested',
                'amount' => $agreementSchedule->amount,
                'currency' => 'INR',
                'purpose' => 'Agreement milestone payment link',
                'expires_at' => now()->addDays(7),
                'paid_at' => null,
                'payment_mode' => null,
                'instrument_number' => null,
                'checksum' => hash('sha256', 'B360PAY-DEMO-10001|'.number_format((float) $agreementSchedule->amount, 2, '.', '').'|'.$booking->id.'|'.$agreementSchedule->id),
                'gateway_payload' => [
                    'provider' => 'prototype',
                    'payment_url' => '/buyer/payment-requests/PAYREQ-10001/pay',
                    'currency' => 'INR',
                    'amount' => (float) $agreementSchedule->amount,
                    'expires_at' => now()->addDays(7)->toISOString(),
                    'simulation_notice' => 'Internal simulated payment link; no external gateway is invoked.',
                ],
                'workflow_history' => [
                    ['status' => 'requested', 'actor_user_id' => $users['suresh.iyer@builder360.test']->id, 'actor' => 'Suresh Iyer', 'note' => 'Buyer payment request created for agreement milestone', 'at' => now()->subHour()->toISOString()],
                ],
                'metadata' => [
                    'source' => 'database_seed',
                    'booking_code' => $booking->booking_code,
                    'schedule_sequence' => $agreementSchedule->sequence,
                ],
            ],
        );

        GstEntry::updateOrCreate(
            ['company_id' => $companies['B360D']->id, 'document_number' => 'RCPT-1001', 'transaction_type' => 'output'],
            [
                'project_id' => $booking->project_id,
                'created_by_user_id' => $users['suresh.iyer@builder360.test']->id,
                'approved_by_user_id' => $users['aditya.mehra@builder360.test']->id,
                'entry_number' => 'GST-10001',
                'period_year' => (int) now()->year,
                'period_month' => (int) now()->month,
                'document_date' => now()->toDateString(),
                'party_name' => $customers['CUS-1001']->name,
                'party_gstin' => null,
                'place_of_supply_state' => 'MH',
                'hsn_sac' => '9954',
                'tax_rate' => 18,
                'taxable_amount' => 423728.81,
                'cgst_amount' => 38135.59,
                'sgst_amount' => 38135.60,
                'igst_amount' => 0,
                'cess_amount' => 0,
                'total_tax_amount' => 76271.19,
                'status' => 'approved',
                'metadata' => [
                    'source' => 'database_seed',
                    'linked_receipt_number' => 'RCPT-1001',
                    'computation_basis' => 'inclusive_receipt_amount_split_for_demo',
                ],
                'workflow_history' => [
                    ['status' => 'submitted', 'actor' => 'Suresh Iyer', 'note' => 'GST output entry submitted', 'at' => now()->subHour()->toISOString()],
                    ['status' => 'approved', 'actor' => 'Aditya Mehra', 'note' => 'GST approval completed', 'at' => now()->toISOString()],
                ],
                'approved_at' => now(),
            ],
        );

        $handover = PossessionHandover::updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'company_id' => $booking->company_id,
                'project_id' => $booking->project_id,
                'customer_id' => $booking->customer_id,
                'project_unit_id' => $booking->project_unit_id,
                'initiated_by_user_id' => $users['priya.nair@builder360.test']->id,
                'completed_by_user_id' => null,
                'handover_number' => 'PH-1001',
                'target_handover_on' => now()->addMonths(2)->toDateString(),
                'actual_handover_on' => null,
                'status' => 'blocked',
                'financial_outstanding' => 13627000,
                'checklist' => [
                    ['code' => 'final_payment_clearance', 'label' => 'Final payment clearance', 'required' => true, 'completed' => false],
                    ['code' => 'documents_verified', 'label' => 'Customer and booking documents verified', 'required' => true, 'completed' => true],
                    ['code' => 'unit_inspection_done', 'label' => 'Unit inspection completed', 'required' => true, 'completed' => false],
                    ['code' => 'keys_ready', 'label' => 'Keys and access cards ready', 'required' => true, 'completed' => false],
                ],
                'blockers' => [
                    ['code' => 'financial_outstanding', 'message' => 'Financial outstanding must be zero before handover.', 'amount' => 13627000],
                    ['code' => 'pending_checklist', 'message' => 'Required checklist items are pending.', 'items' => ['final_payment_clearance', 'unit_inspection_done', 'keys_ready']],
                    ['code' => 'open_snags', 'message' => 'Open snags must be resolved before handover.', 'count' => 1],
                ],
                'possession_letter_reference' => null,
                'workflow_history' => [
                    ['status' => 'initiated', 'actor' => 'Priya Nair', 'note' => 'Possession handover initiated', 'at' => now()->toISOString()],
                    ['status' => 'blocked', 'actor' => 'Priya Nair', 'note' => 'Pending payment, checklist and snag closure', 'at' => now()->toISOString()],
                ],
                'completed_at' => null,
            ],
        );

        HandoverSnag::updateOrCreate(
            ['snag_number' => 'SNAG-1001'],
            [
                'company_id' => $booking->company_id,
                'possession_handover_id' => $handover->id,
                'reported_by_user_id' => $users['priya.nair@builder360.test']->id,
                'resolved_by_user_id' => null,
                'area' => 'Living Room',
                'category' => 'Paint',
                'severity' => 'medium',
                'description' => 'Touch-up required near balcony sliding door.',
                'status' => 'open',
                'target_resolution_on' => now()->addDays(7)->toDateString(),
                'resolved_at' => null,
                'resolution_notes' => null,
                'attachments' => [['name' => 'living-room-paint-snag.jpg', 'url' => 'documents/demo/living-room-paint-snag.jpg']],
                'workflow_history' => [
                    ['status' => 'open', 'actor' => 'Priya Nair', 'note' => 'Handover snag opened', 'at' => now()->toISOString()],
                ],
            ],
        );

        $societyFormation = SocietyFormation::updateOrCreate(
            ['formation_number' => 'SOC-1001'],
            [
                'company_id' => $booking->company_id,
                'project_id' => $booking->project_id,
                'created_by_user_id' => $users['priya.nair@builder360.test']->id,
                'updated_by_user_id' => $users['priya.nair@builder360.test']->id,
                'society_name' => 'Skyline Residency Co-operative Housing Society',
                'association_type' => 'cooperative_society',
                'total_units' => 248,
                'occupied_units' => 154,
                'registration_number' => 'Application filed',
                'application_filed_on' => now()->subDays(21)->toDateString(),
                'registered_on' => null,
                'target_handover_on' => now()->addMonths(3)->toDateString(),
                'status' => 'in_progress',
                'progress_percent' => 64,
                'current_stage' => 'Application filed',
                'next_step' => 'AGM and committee confirmation pending',
                'committee_members' => [
                    ['name' => 'Rohan Shah', 'role' => 'Interim chairperson'],
                    ['name' => 'Neha Sharma', 'role' => 'Treasurer nominee'],
                ],
                'workflow_history' => [
                    ['status' => 'application_filed', 'actor' => 'Priya Nair', 'note' => 'Application filed with registrar', 'at' => now()->subDays(21)->toISOString()],
                    ['status' => 'in_progress', 'actor' => 'Priya Nair', 'note' => 'AGM and committee formation pending', 'at' => now()->toISOString()],
                ],
                'metadata' => ['source' => 'database_seed'],
            ],
        );

        collect([
            ['item_number' => 'CAH-1001', 'facility_name' => 'Clubhouse & Amenities', 'category' => 'amenity', 'checklist_total' => 14, 'checklist_completed' => 14, 'status' => 'complete', 'signed_off_on' => now()->subDays(10)->toDateString()],
            ['item_number' => 'CAH-1002', 'facility_name' => 'Fire-Fighting System', 'category' => 'safety', 'checklist_total' => 12, 'checklist_completed' => 9, 'status' => 'in_progress', 'signed_off_on' => null],
            ['item_number' => 'CAH-1003', 'facility_name' => 'STP / Water Treatment', 'category' => 'utility', 'checklist_total' => 9, 'checklist_completed' => 4, 'status' => 'pending_snags', 'signed_off_on' => null],
        ])->each(function (array $item) use ($booking, $societyFormation, $users): void {
            CommonAreaHandoverItem::updateOrCreate(
                ['item_number' => $item['item_number']],
                [
                    'company_id' => $booking->company_id,
                    'project_id' => $booking->project_id,
                    'society_formation_id' => $societyFormation->id,
                    'responsible_user_id' => $users['rajesh.kulkarni@builder360.test']->id,
                    'signed_off_by_user_id' => $item['status'] === 'complete' ? $users['aditya.mehra@builder360.test']->id : null,
                    'facility_name' => $item['facility_name'],
                    'category' => $item['category'],
                    'checklist_total' => $item['checklist_total'],
                    'checklist_completed' => $item['checklist_completed'],
                    'status' => $item['status'],
                    'target_completion_on' => now()->addDays(30)->toDateString(),
                    'signed_off_on' => $item['signed_off_on'],
                    'snag_summary' => $item['status'] === 'pending_snags'
                        ? [['severity' => 'medium', 'note' => 'Water recycling test report pending']]
                        : [],
                    'workflow_history' => [
                        ['status' => $item['status'], 'actor' => 'Rajesh Kulkarni', 'note' => 'Common-area handover status updated', 'at' => now()->toISOString()],
                    ],
                    'metadata' => ['source' => 'database_seed'],
                ],
            );
        });

        MaintenanceDue::updateOrCreate(
            ['due_number' => 'MDU-1001'],
            [
                'company_id' => $booking->company_id,
                'project_id' => $booking->project_id,
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'project_unit_id' => $booking->project_unit_id,
                'raised_by_user_id' => $users['suresh.iyer@builder360.test']->id,
                'paid_by_user_id' => null,
                'period_start_on' => now()->startOfQuarter()->toDateString(),
                'period_end_on' => now()->startOfQuarter()->addMonths(2)->endOfMonth()->toDateString(),
                'due_on' => now()->startOfQuarter()->addDays(15)->toDateString(),
                'amount' => 16200,
                'paid_amount' => 0,
                'balance_amount' => 16200,
                'status' => now()->gt(now()->startOfQuarter()->addDays(15)) ? 'overdue' : 'due',
                'paid_at' => null,
                'payment_reference' => null,
                'last_reminded_at' => now()->subDays(2),
                'workflow_history' => [
                    ['status' => 'due', 'actor' => 'Suresh Iyer', 'note' => 'Quarterly maintenance demand created', 'at' => now()->subDays(15)->toISOString()],
                    ['status' => 'reminded', 'actor' => 'Priya Nair', 'note' => 'Reminder sent for unpaid maintenance due', 'at' => now()->subDays(2)->toISOString()],
                ],
                'metadata' => ['source' => 'database_seed'],
            ],
        );

        $serviceTicket = ServiceTicket::updateOrCreate(
            ['ticket_number' => 'AST-1001'],
            [
                'company_id' => $booking->company_id,
                'project_id' => $booking->project_id,
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'project_unit_id' => $booking->project_unit_id,
                'raised_by_user_id' => $users['rohan.shah@example.test']->id,
                'assigned_to_user_id' => $users['rajesh.kulkarni@builder360.test']->id,
                'closed_by_user_id' => null,
                'category' => 'defect',
                'priority' => 'high',
                'source' => 'portal',
                'subject' => 'Kitchen sink leakage after handover inspection',
                'description' => 'Water seepage noticed under the kitchen sink cabinet and requires maintenance inspection.',
                'status' => 'in_progress',
                'first_response_due_at' => now()->addHours(12),
                'first_responded_at' => now(),
                'sla_due_at' => now()->addHours(24),
                'resolved_at' => null,
                'closed_at' => null,
                'resolution_summary' => null,
                'customer_rating' => null,
                'attachments' => [['name' => 'kitchen-sink-leakage.jpg', 'url' => 'documents/demo/kitchen-sink-leakage.jpg']],
                'workflow_history' => [
                    ['status' => 'open', 'actor' => 'Rohan Shah', 'note' => 'Buyer service ticket opened', 'at' => now()->subHours(2)->toISOString()],
                    ['status' => 'assigned', 'actor' => 'Priya Nair', 'note' => 'Assigned to construction team', 'at' => now()->subHour()->toISOString()],
                    ['status' => 'in_progress', 'actor' => 'Rajesh Kulkarni', 'note' => 'Maintenance work order created', 'at' => now()->toISOString()],
                ],
                'metadata' => ['source' => 'database_seed', 'sla_hours' => 24],
            ],
        );

        MaintenanceWorkOrder::updateOrCreate(
            ['work_order_number' => 'MWO-1001'],
            [
                'company_id' => $booking->company_id,
                'service_ticket_id' => $serviceTicket->id,
                'project_unit_id' => $booking->project_unit_id,
                'assigned_to_user_id' => $users['rajesh.kulkarni@builder360.test']->id,
                'vendor_id' => null,
                'status' => 'scheduled',
                'scheduled_on' => now()->addDay()->toDateString(),
                'scope_of_work' => 'Inspect kitchen sink plumbing, repair leakage, seal cabinet edge and upload completion note.',
                'estimated_cost' => 3500,
                'actual_cost' => 0,
                'materials_required' => [
                    ['item' => 'PVC sealant', 'quantity' => 1, 'uom' => 'tube'],
                    ['item' => 'Flexible waste pipe', 'quantity' => 1, 'uom' => 'piece'],
                ],
                'completion_notes' => null,
                'completed_at' => null,
                'workflow_history' => [
                    ['status' => 'scheduled', 'actor' => 'Rajesh Kulkarni', 'note' => 'Maintenance work order scheduled', 'at' => now()->toISOString()],
                ],
            ],
        );

        collect([
            [
                'notification_number' => 'NTF-10001',
                'recipient' => 'priya.nair@builder360.test',
                'triggered_by' => 'rohan.shah@example.test',
                'category' => 'after_sales',
                'severity' => 'warning',
                'status' => 'unread',
                'title' => 'High-priority service ticket requires follow-up',
                'body' => 'Ticket AST-1001 is in progress and must be monitored before SLA breach.',
                'action_url' => '/after-sales/tickets?status=in_progress',
                'notifiable_type' => ServiceTicket::class,
                'notifiable_id' => $serviceTicket->id,
                'payload' => ['ticket_number' => 'AST-1001', 'sla_due_at' => $serviceTicket->sla_due_at?->toISOString()],
            ],
            [
                'notification_number' => 'NTF-10002',
                'recipient' => 'rajesh.kulkarni@builder360.test',
                'triggered_by' => 'priya.nair@builder360.test',
                'category' => 'maintenance',
                'severity' => 'critical',
                'status' => 'unread',
                'title' => 'Maintenance work order scheduled',
                'body' => 'MWO-1001 is scheduled for tomorrow. Complete the work order before resolving the customer ticket.',
                'action_url' => '/after-sales/work-orders?status=scheduled',
                'notifiable_type' => MaintenanceWorkOrder::class,
                'notifiable_id' => MaintenanceWorkOrder::where('work_order_number', 'MWO-1001')->firstOrFail()->id,
                'payload' => ['work_order_number' => 'MWO-1001', 'ticket_number' => 'AST-1001'],
            ],
            [
                'notification_number' => 'NTF-10003',
                'recipient' => 'suresh.iyer@builder360.test',
                'triggered_by' => 'priya.nair@builder360.test',
                'category' => 'collections',
                'severity' => 'info',
                'status' => 'read',
                'title' => 'Collection receipt approved',
                'body' => 'RCPT-1001 has been approved and reflected in collection reporting.',
                'action_url' => '/finance/collections?status=approved',
                'notifiable_type' => CollectionReceipt::class,
                'notifiable_id' => CollectionReceipt::where('receipt_number', 'RCPT-1001')->firstOrFail()->id,
                'payload' => ['receipt_number' => 'RCPT-1001'],
                'read_at' => now(),
            ],
            [
                'notification_number' => 'NTF-QA-DIRECTOR-APPROVAL',
                'recipient' => 'aditya.mehra@builder360.test',
                'triggered_by' => 'suresh.iyer@builder360.test',
                'category' => 'approval',
                'severity' => 'warning',
                'status' => 'unread',
                'title' => 'Approval reminder for Director',
                'body' => 'Approval reminder ready for review, read and archive actions.',
                'action_url' => '/#approvals',
                'notifiable_type' => null,
                'notifiable_id' => null,
                'payload' => ['source' => 'sample_setup', 'role' => 'director'],
            ],
            [
                'notification_number' => 'NTF-QA-EMPLOYEE-INVENTORY',
                'recipient' => 'amit.verma@builder360.test',
                'triggered_by' => 'rajesh.kulkarni@builder360.test',
                'category' => 'inventory',
                'severity' => 'info',
                'status' => 'read',
                'title' => 'Inventory reminder for Employee',
                'body' => 'Employee reminder available for your account.',
                'action_url' => '/#notifications',
                'notifiable_type' => null,
                'notifiable_id' => null,
                'payload' => ['source' => 'sample_setup', 'role' => 'employee'],
                'read_at' => now(),
            ],
            [
                'notification_number' => 'NTF-QA-BUYER-PAYMENT',
                'recipient' => 'rohan.shah@example.test',
                'triggered_by' => 'suresh.iyer@builder360.test',
                'category' => 'payment',
                'severity' => 'info',
                'status' => 'unread',
                'title' => 'Payment reminder for Buyer',
                'body' => 'Buyer payment reminder available for review.',
                'action_url' => '/#buyer',
                'notifiable_type' => null,
                'notifiable_id' => null,
                'payload' => ['source' => 'sample_setup', 'role' => 'buyer'],
            ],
            [
                'notification_number' => 'NTF-QA-CHANNEL-SALES',
                'recipient' => 'sameer.bafna@partners.builder360.test',
                'triggered_by' => 'priya.nair@builder360.test',
                'category' => 'sales',
                'severity' => 'success',
                'status' => 'archived',
                'title' => 'Sales notification for Channel Partner',
                'body' => 'Partner notification archived for reference.',
                'action_url' => '/#leads',
                'notifiable_type' => null,
                'notifiable_id' => null,
                'payload' => ['source' => 'sample_setup', 'role' => 'channel_partner'],
                'read_at' => now()->subHour(),
                'archived_at' => now(),
            ],
            [
                'notification_number' => 'NTF-QA-BROKER-LEGAL',
                'recipient' => 'farhan.shaikh@partners.builder360.test',
                'triggered_by' => 'meera.kapoor@builder360.test',
                'category' => 'legal',
                'severity' => 'critical',
                'status' => 'unread',
                'title' => 'Legal reminder for Executive Partner Broker',
                'body' => 'Broker legal reminder available for review.',
                'action_url' => '/#documents',
                'notifiable_type' => null,
                'notifiable_id' => null,
                'payload' => ['source' => 'sample_setup', 'role' => 'executive_partner_broker'],
            ],
            [
                'notification_number' => 'NTF-QA-HR-APPROVAL',
                'recipient' => 'deepa.rao@builder360.test',
                'triggered_by' => 'amit.verma@builder360.test',
                'category' => 'approval',
                'severity' => 'warning',
                'status' => 'unread',
                'title' => 'Employee approval reminder',
                'body' => 'Employee request is awaiting HR review.',
                'action_url' => '/#approvals',
                'notifiable_type' => null,
                'notifiable_id' => null,
                'payload' => ['source' => 'sample_setup', 'role' => 'hr_manager'],
            ],
            [
                'notification_number' => 'NTF-QA-PAYROLL-PAYMENT',
                'recipient' => 'kavita.shah@builder360.test',
                'triggered_by' => 'suresh.iyer@builder360.test',
                'category' => 'payment',
                'severity' => 'warning',
                'status' => 'unread',
                'title' => 'Payroll payment review',
                'body' => 'Payroll payment batch is ready for review.',
                'action_url' => '/#hr/payroll',
                'notifiable_type' => null,
                'notifiable_id' => null,
                'payload' => ['source' => 'sample_setup', 'role' => 'payroll'],
            ],
            [
                'notification_number' => 'NTF-QA-RECRUITER-SALES',
                'recipient' => 'ananya.sen@builder360.test',
                'triggered_by' => 'deepa.rao@builder360.test',
                'category' => 'sales',
                'severity' => 'info',
                'status' => 'unread',
                'title' => 'Interview follow-up reminder',
                'body' => 'Candidate follow-up is due today.',
                'action_url' => '/#hr/recruitment',
                'notifiable_type' => null,
                'notifiable_id' => null,
                'payload' => ['source' => 'sample_setup', 'role' => 'recruiter'],
            ],
            [
                'notification_number' => 'NTF-QA-COMPLIANCE-LEGAL',
                'recipient' => 'meera.kapoor@builder360.test',
                'triggered_by' => 'aditya.mehra@builder360.test',
                'category' => 'legal',
                'severity' => 'critical',
                'status' => 'unread',
                'title' => 'Compliance filing reminder',
                'body' => 'Legal compliance filing requires attention.',
                'action_url' => '/#legal',
                'notifiable_type' => null,
                'notifiable_id' => null,
                'payload' => ['source' => 'sample_setup', 'role' => 'compliance'],
            ],
            [
                'notification_number' => 'NTF-QA-AUDITOR-LEGAL',
                'recipient' => 'ishaan.trivedi@builder360.test',
                'triggered_by' => 'meera.kapoor@builder360.test',
                'category' => 'legal',
                'severity' => 'info',
                'status' => 'read',
                'title' => 'Activity review available',
                'body' => 'Recent legal activity is available for review.',
                'action_url' => '/#audit',
                'notifiable_type' => null,
                'notifiable_id' => null,
                'payload' => ['source' => 'sample_setup', 'role' => 'auditor'],
                'read_at' => now(),
            ],
            [
                'notification_number' => 'NTF-QA-SYSTEM-APPROVAL',
                'recipient' => 'nikhil.desai@builder360.test',
                'triggered_by' => 'aditya.mehra@builder360.test',
                'category' => 'approval',
                'severity' => 'success',
                'status' => 'unread',
                'title' => 'System setup approval reminder',
                'body' => 'System approval queue has a setup item ready for review.',
                'action_url' => '/#admin',
                'notifiable_type' => null,
                'notifiable_id' => null,
                'payload' => ['source' => 'sample_setup', 'role' => 'system_admin'],
            ],
        ])->each(function (array $notification) use ($companies, $users): void {
            $recipient = $users[$notification['recipient']];
            $triggeredBy = $users[$notification['triggered_by']];
            unset($notification['recipient'], $notification['triggered_by']);

            UserNotification::updateOrCreate(
                ['notification_number' => $notification['notification_number']],
                $notification + [
                    'company_id' => $companies['B360D']->id,
                    'recipient_user_id' => $recipient->id,
                    'triggered_by_user_id' => $triggeredBy->id,
                    'channel' => 'in_app',
                ],
            );
        });

        $seedVoucher = FinancialVoucher::updateOrCreate(
            ['voucher_number' => 'JV-10001'],
            [
                'company_id' => $companies['B360D']->id,
                'project_id' => $projects['SKY-PUN']->id,
                'created_by_user_id' => $users['suresh.iyer@builder360.test']->id,
                'approved_by_user_id' => $users['aditya.mehra@builder360.test']->id,
                'voucher_type' => 'journal',
                'status' => 'approved',
                'voucher_date' => now()->toDateString(),
                'reference_number' => 'RCPT-1001',
                'narration' => 'Revenue recognition entry for approved Skyline booking collection.',
                'currency' => 'INR',
                'total_debit' => 2500000,
                'total_credit' => 2500000,
                'tax_summary' => ['total_tax_amount' => 0, 'line_count' => 2],
                'workflow_history' => [
                    ['status' => 'submitted', 'actor_user_id' => $users['suresh.iyer@builder360.test']->id, 'actor' => 'Suresh Iyer', 'note' => 'Financial voucher submitted', 'at' => now()->subHours(2)->toISOString()],
                    ['status' => 'approved', 'actor_user_id' => $users['aditya.mehra@builder360.test']->id, 'actor' => 'Aditya Mehra', 'note' => 'Director approval completed', 'at' => now()->subHour()->toISOString()],
                ],
                'metadata' => ['source' => 'database_seed', 'linked_receipt_number' => 'RCPT-1001'],
                'approved_at' => now()->subHour(),
                'rejected_at' => null,
            ],
        );

        $seedVoucher->lines()->delete();
        collect([
            [
                'line_number' => 1,
                'account_code' => 'BANK-HDFC-001',
                'account_name' => 'HDFC Bank Collection Account',
                'line_type' => 'debit',
                'amount' => 2500000,
                'party_type' => Customer::class,
                'party_id' => $customers['CUS-1001']->id,
                'cost_center' => 'Sales Collections',
                'description' => 'Bank receipt against Skyline booking collection.',
            ],
            [
                'line_number' => 2,
                'account_code' => 'ADV-CUST-SKY',
                'account_name' => 'Customer Advances - Skyline Residency',
                'line_type' => 'credit',
                'amount' => 2500000,
                'party_type' => Customer::class,
                'party_id' => $customers['CUS-1001']->id,
                'cost_center' => 'Sales Collections',
                'description' => 'Customer advance recognized for approved receipt.',
            ],
        ])->each(fn (array $line) => $seedVoucher->lines()->create($line + [
            'project_id' => $projects['SKY-PUN']->id,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'metadata' => ['source' => 'database_seed'],
        ]));

        WorkTask::updateOrCreate(
            ['task_number' => 'TSK-10001'],
            [
                'company_id' => $companies['B360D']->id,
                'project_id' => $projects['SKY-PUN']->id,
                'created_by_user_id' => $users['priya.nair@builder360.test']->id,
                'assigned_to_user_id' => $users['rajesh.kulkarni@builder360.test']->id,
                'title' => 'Confirm Skyline site-visit readiness',
                'description' => 'Coordinate with construction team and confirm model-flat readiness before weekend buyer visits.',
                'priority' => 'high',
                'status' => 'open',
                'due_at' => now()->addDays(2)->setTime(17, 0),
                'started_at' => null,
                'completed_at' => null,
                'module_context' => 'site_visits',
                'related_type' => Project::class,
                'related_id' => $projects['SKY-PUN']->id,
                'checklist' => [
                    ['label' => 'Confirm cleaning status', 'done' => false],
                    ['label' => 'Verify access route and signage', 'done' => false],
                    ['label' => 'Share confirmation with sales team', 'done' => false],
                ],
                'workflow_history' => [
                    ['status' => 'open', 'actor_user_id' => $users['priya.nair@builder360.test']->id, 'actor' => 'Priya Nair', 'note' => 'Collaboration task opened', 'at' => now()->subHours(2)->toISOString()],
                ],
                'metadata' => ['source' => 'database_seed'],
            ],
        );

        CalendarEvent::updateOrCreate(
            ['event_number' => 'CAL-10001'],
            [
                'company_id' => $companies['B360D']->id,
                'project_id' => $projects['SKY-PUN']->id,
                'organizer_user_id' => $users['priya.nair@builder360.test']->id,
                'title' => 'Skyline sales and construction coordination',
                'description' => 'Weekly alignment for site visits, payment follow-ups, readiness risks and customer handover priorities.',
                'event_type' => 'meeting',
                'status' => 'scheduled',
                'starts_at' => now()->addDays(6)->setTime(10, 0),
                'ends_at' => now()->addDays(6)->setTime(11, 0),
                'timezone' => 'Asia/Kolkata',
                'location' => 'Pune Head Office',
                'meeting_url' => null,
                'visibility' => 'internal',
                'attendees' => [
                    ['user_id' => $users['priya.nair@builder360.test']->id, 'name' => 'Priya Nair', 'email' => 'priya.nair@builder360.test', 'response' => 'accepted'],
                    ['user_id' => $users['rajesh.kulkarni@builder360.test']->id, 'name' => 'Rajesh Kulkarni', 'email' => 'rajesh.kulkarni@builder360.test', 'response' => 'pending'],
                    ['user_id' => $users['suresh.iyer@builder360.test']->id, 'name' => 'Suresh Iyer', 'email' => 'suresh.iyer@builder360.test', 'response' => 'pending'],
                ],
                'reminders' => [
                    ['minutes_before' => 1440],
                    ['minutes_before' => 30],
                ],
                'related_type' => Project::class,
                'related_id' => $projects['SKY-PUN']->id,
                'workflow_history' => [
                    ['status' => 'scheduled', 'actor_user_id' => $users['priya.nair@builder360.test']->id, 'actor' => 'Priya Nair', 'note' => 'Coordination calendar event scheduled', 'at' => now()->subHour()->toISOString()],
                ],
                'metadata' => ['source' => 'database_seed'],
            ],
        );

        CollaborationMessage::updateOrCreate(
            ['message_number' => 'MSG-10001'],
            [
                'company_id' => $companies['B360D']->id,
                'project_id' => $projects['SKY-PUN']->id,
                'parent_message_id' => null,
                'sender_user_id' => $users['priya.nair@builder360.test']->id,
                'recipient_user_id' => $users['suresh.iyer@builder360.test']->id,
                'thread_key' => 'THR-10001',
                'subject' => 'Skyline payment follow-up coordination',
                'body' => 'Please review the agreement milestone payment link for the Skyline booking before the weekend follow-up.',
                'priority' => 'high',
                'status' => 'unread',
                'read_at' => null,
                'recipient_archived_at' => null,
                'metadata' => [
                    'source' => 'database_seed',
                    'related_booking_code' => 'BK-1001',
                    'module_context' => 'collections',
                ],
            ],
        );

        ManagedDocument::updateOrCreate(
            ['document_number' => 'DOC-1001'],
            [
                'company_id' => $companies['B360D']->id,
                'document_category_id' => $documentCategories['RERA_CERT']->id,
                'uploaded_by_user_id' => $users['priya.nair@builder360.test']->id,
                'approved_by_user_id' => $users['suresh.iyer@builder360.test']->id,
                'title' => 'Skyline Residency RERA Certificate',
                'owner_type' => 'project',
                'owner_id' => $projects['SKY-PUN']->id,
                'status' => 'approved',
                'storage_disk' => 'local',
                'storage_path' => 'documents/demo/skyline-rera-certificate.pdf',
                'original_filename' => 'skyline-rera-certificate.pdf',
                'mime_type' => 'application/pdf',
                'file_size_bytes' => 245760,
                'checksum_sha256' => hash('sha256', 'DOC-1001'),
                'issue_date' => now()->subMonths(3)->toDateString(),
                'expires_on' => now()->addDays(25)->toDateString(),
                'version' => 1,
                'is_current' => true,
                'metadata' => ['source' => 'database_seed', 'document_reference' => 'RERA-DEMO-SKY'],
                'approved_at' => now(),
            ],
        );

        ManagedDocument::updateOrCreate(
            ['document_number' => 'DOC-1002'],
            [
                'company_id' => $companies['B360D']->id,
                'document_category_id' => $documentCategories['BOOKING_FORM']->id,
                'uploaded_by_user_id' => $users['priya.nair@builder360.test']->id,
                'approved_by_user_id' => $users['suresh.iyer@builder360.test']->id,
                'title' => 'Rohan Shah Booking Form',
                'owner_type' => 'booking',
                'owner_id' => $booking->id,
                'status' => 'approved',
                'storage_disk' => 'local',
                'storage_path' => 'documents/demo/rohan-shah-booking-form.pdf',
                'original_filename' => 'rohan-shah-booking-form.pdf',
                'mime_type' => 'application/pdf',
                'file_size_bytes' => 184320,
                'checksum_sha256' => hash('sha256', 'DOC-1002'),
                'issue_date' => $booking->booked_on,
                'expires_on' => null,
                'version' => 1,
                'is_current' => true,
                'metadata' => ['source' => 'database_seed', 'buyer_visible' => true],
                'approved_at' => now(),
            ],
        );

        ManagedDocument::updateOrCreate(
            ['document_number' => 'DOC-1003'],
            [
                'company_id' => $companies['B360D']->id,
                'document_category_id' => $documentCategories['CUSTOMER_KYC']->id,
                'uploaded_by_user_id' => $users['priya.nair@builder360.test']->id,
                'approved_by_user_id' => $users['suresh.iyer@builder360.test']->id,
                'title' => 'Rohan Shah Customer KYC',
                'owner_type' => 'customer',
                'owner_id' => $customers['CUS-1001']->id,
                'status' => 'approved',
                'storage_disk' => 'local',
                'storage_path' => 'documents/demo/rohan-shah-kyc.pdf',
                'original_filename' => 'rohan-shah-kyc.pdf',
                'mime_type' => 'application/pdf',
                'file_size_bytes' => 143360,
                'checksum_sha256' => hash('sha256', 'DOC-1003'),
                'issue_date' => now()->subMonths(4)->toDateString(),
                'expires_on' => now()->addYear()->toDateString(),
                'version' => 1,
                'is_current' => true,
                'metadata' => ['source' => 'database_seed', 'buyer_visible' => true],
                'approved_at' => now(),
            ],
        );

        collect([
            ['employee_code' => 'EMP-0012', 'user' => 'rajesh.kulkarni@builder360.test', 'company' => 'B360D', 'branch' => 'PNQ-HO', 'project' => 'SKY-PUN', 'name' => 'Rajesh Kulkarni', 'designation' => 'Project Manager', 'department' => 'Construction', 'grade' => 'A', 'monthly_ctc' => 124000],
            ['employee_code' => 'EMP-0021', 'user' => 'priya.nair@builder360.test', 'company' => 'B360D', 'branch' => 'PNQ-HO', 'project' => 'SKY-PUN', 'name' => 'Priya Nair', 'designation' => 'Sales Head', 'department' => 'Sales', 'grade' => 'M1', 'monthly_ctc' => 168000],
            ['employee_code' => 'EMP-0018', 'user' => 'deepa.rao@builder360.test', 'company' => 'B360D', 'branch' => 'PNQ-HO', 'project' => null, 'name' => 'Deepa Rao', 'designation' => 'HR Manager', 'department' => 'HR', 'grade' => 'M1', 'monthly_ctc' => 105000],
            ['employee_code' => 'EMP-0030', 'user' => 'amit.verma@builder360.test', 'company' => 'B360D', 'branch' => 'PNQ-HO', 'project' => 'SKY-PUN', 'name' => 'Amit Verma', 'designation' => 'Site Engineer', 'department' => 'Construction', 'grade' => 'B1', 'monthly_ctc' => 62000],
        ])->each(function (array $employee) use ($companies, $branches, $projects, $users) {
            Employee::updateOrCreate(
                ['employee_code' => $employee['employee_code']],
                [
                    'user_id' => $users[$employee['user']]->id,
                    'company_id' => $companies[$employee['company']]->id,
                    'branch_id' => $branches[$employee['branch']]->id,
                    'project_id' => $employee['project'] ? $projects[$employee['project']]->id : null,
                    'name' => $employee['name'],
                    'designation' => $employee['designation'],
                    'department' => $employee['department'],
                    'grade' => $employee['grade'],
                    'employment_type' => 'full_time',
                    'status' => 'active',
                    'joined_on' => '2021-04-01',
                    'statutory_state' => $companies[$employee['company']]->state,
                    'monthly_ctc' => $employee['monthly_ctc'],
                    'sensitive_profile' => [
                        'pan_masked' => 'ABCDE••••F',
                        'aadhaar_masked' => '•••• •••• 1234',
                        'bank_beneficiary_name' => $employee['name'],
                        'bank_account_number' => match ($employee['employee_code']) {
                            'EMP-0012' => '100200300401',
                            'EMP-0021' => '100200300402',
                            'EMP-0018' => '100200300403',
                            'EMP-0030' => '100200300404',
                            default => '100200300499',
                        },
                        'bank_ifsc' => 'HDFC0001234',
                    ],
                ],
            );
        });

        $seededEmployees = Employee::whereIn('employee_code', ['EMP-0012', 'EMP-0021', 'EMP-0018', 'EMP-0030'])
            ->get()
            ->keyBy('employee_code');

        $seededEmployees['EMP-0012']->forceFill(['manager_employee_id' => $seededEmployees['EMP-0018']->id])->save();
        $seededEmployees['EMP-0030']->forceFill(['manager_employee_id' => $seededEmployees['EMP-0012']->id])->save();

        $performanceCycle = PerformanceCycle::updateOrCreate(
            ['cycle_code' => 'PFC-10001'],
            [
                'company_id' => $companies['B360D']->id,
                'project_id' => $projects['SKY-PUN']->id,
                'created_by_user_id' => $users['deepa.rao@builder360.test']->id,
                'activated_by_user_id' => $users['deepa.rao@builder360.test']->id,
                'name' => 'Skyline Construction Q1 Performance Review',
                'frequency' => 'quarterly',
                'status' => 'active',
                'starts_on' => now()->startOfQuarter()->toDateString(),
                'ends_on' => now()->endOfQuarter()->toDateString(),
                'review_due_on' => now()->endOfQuarter()->addDays(10)->toDateString(),
                'department' => 'Construction',
                'rating_scale_min' => 1,
                'rating_scale_max' => 5,
                'passing_score' => 3,
                'rules' => [
                    'kpi_weight_percent' => 70,
                    'kra_weight_percent' => 30,
                    'pip_threshold' => 2.5,
                    'approval_chain' => ['employee_self_review', 'reporting_manager', 'hr_manager'],
                ],
                'workflow_history' => [
                    ['status' => 'active', 'actor_user_id' => $users['deepa.rao@builder360.test']->id, 'actor' => 'Deepa Rao', 'note' => 'Quarterly performance cycle activated', 'at' => now()->subDays(3)->toISOString()],
                ],
                'activated_at' => now()->subDays(3),
            ],
        );

        PerformanceReview::updateOrCreate(
            ['review_number' => 'PFR-10001'],
            [
                'company_id' => $companies['B360D']->id,
                'performance_cycle_id' => $performanceCycle->id,
                'employee_id' => $seededEmployees['EMP-0030']->id,
                'manager_employee_id' => $seededEmployees['EMP-0012']->id,
                'self_reviewer_user_id' => null,
                'manager_reviewer_user_id' => null,
                'hr_reviewer_user_id' => null,
                'status' => 'draft',
                'legacy_manual_scoring' => true,
                'period_start' => $performanceCycle->starts_on,
                'period_end' => $performanceCycle->ends_on,
                'kpis' => [
                    ['name' => 'Daily progress reporting quality', 'target' => 'Submit accurate DPR inputs within cut-off time', 'weight' => 40, 'metric' => 'timeliness_quality'],
                    ['name' => 'Site safety observations', 'target' => 'Zero unresolved high-risk safety observations', 'weight' => 30, 'metric' => 'safety'],
                    ['name' => 'Contractor coordination', 'target' => 'Resolve assigned contractor blockers within SLA', 'weight' => 30, 'metric' => 'sla'],
                ],
                'kra_summary' => ['role_expectation' => 'Construction execution support for Skyline Residency.'],
                'workflow_history' => [
                    ['status' => 'draft', 'actor_user_id' => $users['deepa.rao@builder360.test']->id, 'actor' => 'Deepa Rao', 'note' => 'Performance review draft created', 'at' => now()->subDays(2)->toISOString()],
                ],
            ],
        );

        EmployeeConfirmationCase::updateOrCreate(
            ['case_number' => 'CNF-10001'],
            [
                'company_id' => $companies['B360D']->id,
                'employee_id' => $seededEmployees['EMP-0030']->id,
                'manager_employee_id' => $seededEmployees['EMP-0012']->id,
                'created_by_user_id' => $users['deepa.rao@builder360.test']->id,
                'manager_reviewer_user_id' => null,
                'hr_reviewer_user_id' => null,
                'status' => 'due',
                'probation_starts_on' => now()->subMonths(6)->startOfMonth()->toDateString(),
                'probation_ends_on' => now()->subDay()->toDateString(),
                'review_due_on' => now()->addDays(3)->toDateString(),
                'manager_recommendation' => null,
                'manager_comments' => null,
                'review_scores' => null,
                'hr_decision' => null,
                'hr_comments' => null,
                'confirmation_effective_on' => null,
                'extended_until' => null,
                'confirmation_letter_reference' => null,
                'workflow_history' => [
                    ['status' => 'due', 'actor_user_id' => $users['deepa.rao@builder360.test']->id, 'actor' => 'Deepa Rao', 'note' => 'Probation confirmation case due', 'at' => now()->subDay()->toISOString()],
                ],
                'manager_submitted_at' => null,
                'hr_decided_at' => null,
            ],
        );

        EmployeeExitInterview::updateOrCreate(
            ['interview_number' => 'EXI-10001'],
            [
                'company_id' => $companies['B360D']->id,
                'employee_id' => $seededEmployees['EMP-0030']->id,
                'employee_separation_settlement_id' => null,
                'scheduled_by_user_id' => $users['deepa.rao@builder360.test']->id,
                'submitted_by_user_id' => $users['amit.verma@builder360.test']->id,
                'reviewed_by_user_id' => null,
                'status' => 'submitted',
                'interview_due_on' => now()->addDays(3)->toDateString(),
                'submitted_at' => now()->subHours(2),
                'reviewed_at' => null,
                'separation_reason' => 'career_growth',
                'rehire_recommendation' => 'yes',
                'overall_experience_rating' => 4,
                'manager_relationship_rating' => 4,
                'workload_rating' => 3,
                'compensation_rating' => 3,
                'public_feedback' => 'The project team was supportive and the handover process was clear.',
                'improvement_suggestions' => 'Improve career-path communication for site engineers.',
                'confidential_responses' => [
                    'primary_reason' => 'Accepted a role with broader site planning responsibility.',
                    'work_experience' => 'Positive overall, with scope for stronger mentoring cadence.',
                    'manager_feedback' => 'Manager was accessible during critical site milestones.',
                    'improvement_opportunities' => 'More structured role progression and learning plans.',
                    'rehire_context' => 'Would consider returning for a senior project role.',
                ],
                'risk_flags' => ['retention_risk'],
                'questionnaire_template' => [
                    ['key' => 'primary_reason', 'label' => 'Primary reason for leaving', 'type' => 'choice'],
                    ['key' => 'work_experience', 'label' => 'How would you describe your work experience?', 'type' => 'text'],
                    ['key' => 'manager_feedback', 'label' => 'Feedback about manager and support received', 'type' => 'text'],
                    ['key' => 'improvement_opportunities', 'label' => 'What should the company improve?', 'type' => 'text'],
                    ['key' => 'rehire_context', 'label' => 'Would you consider rejoining in future?', 'type' => 'choice'],
                ],
                'hr_review_notes' => null,
                'action_items' => [],
                'workflow_history' => [
                    ['status' => 'scheduled', 'actor_user_id' => $users['deepa.rao@builder360.test']->id, 'actor' => 'Deepa Rao', 'note' => 'Exit interview scheduled', 'at' => now()->subDay()->toISOString()],
                    ['status' => 'submitted', 'actor_user_id' => $users['amit.verma@builder360.test']->id, 'actor' => 'Amit Verma', 'note' => 'Exit interview submitted', 'at' => now()->subHours(2)->toISOString()],
                ],
            ],
        );

        EmployeeAsset::updateOrCreate(
            ['asset_code' => 'AST-LAP-1001'],
            [
                'company_id' => $companies['B360D']->id,
                'employee_id' => $seededEmployees['EMP-0030']->id,
                'assigned_by_user_id' => $users['deepa.rao@builder360.test']->id,
                'recovered_by_user_id' => null,
                'category' => 'Laptop',
                'name' => 'Dell Latitude 5440',
                'serial_number' => 'DL-5440-B360-001',
                'status' => 'assigned',
                'condition' => 'good',
                'assigned_on' => now()->subMonths(2)->toDateString(),
                'recovered_on' => null,
                'estimated_value' => 78000,
                'metadata' => ['source' => 'database_seed', 'warranty_until' => now()->addYears(2)->toDateString()],
                'workflow_history' => [
                    ['status' => 'available', 'actor_user_id' => $users['deepa.rao@builder360.test']->id, 'actor' => 'Deepa Rao', 'note' => 'Asset registered', 'at' => now()->subMonths(2)->toISOString()],
                    ['status' => 'assigned', 'actor_user_id' => $users['deepa.rao@builder360.test']->id, 'actor' => 'Deepa Rao', 'note' => 'Asset assigned to Amit Verma', 'at' => now()->subMonths(2)->toISOString()],
                ],
            ],
        );

        EmployeeAsset::updateOrCreate(
            ['asset_code' => 'AST-MOB-1002'],
            [
                'company_id' => $companies['B360D']->id,
                'employee_id' => null,
                'assigned_by_user_id' => null,
                'recovered_by_user_id' => null,
                'category' => 'Mobile',
                'name' => 'Android Field Device',
                'serial_number' => 'MOB-B360-002',
                'status' => 'available',
                'condition' => 'new',
                'assigned_on' => null,
                'recovered_on' => null,
                'estimated_value' => 22000,
                'metadata' => ['source' => 'database_seed', 'inventory_location' => 'Pune Head Office'],
                'workflow_history' => [
                    ['status' => 'available', 'actor_user_id' => $users['deepa.rao@builder360.test']->id, 'actor' => 'Deepa Rao', 'note' => 'Asset available', 'at' => now()->toISOString()],
                ],
            ],
        );

        ExpenseClaim::updateOrCreate(
            ['claim_number' => 'CLM-1001'],
            [
                'company_id' => $companies['B360D']->id,
                'employee_id' => $seededEmployees['EMP-0030']->id,
                'requested_by_user_id' => $users['amit.verma@builder360.test']->id,
                'approved_by_user_id' => null,
                'paid_by_user_id' => null,
                'claim_type' => 'travel',
                'status' => 'submitted',
                'claim_date' => now()->subDays(2)->toDateString(),
                'amount' => 1850,
                'approved_amount' => 0,
                'currency' => 'INR',
                'description' => 'Site travel reimbursement for vendor inspection visit.',
                'attachments' => [['name' => 'taxi-receipt.pdf', 'url' => 'documents/demo/taxi-receipt.pdf']],
                'decision_note' => null,
                'workflow_history' => [
                    ['status' => 'submitted', 'actor_user_id' => $users['amit.verma@builder360.test']->id, 'actor' => 'Amit Verma', 'note' => 'Travel claim submitted', 'at' => now()->subDays(2)->toISOString()],
                ],
                'approved_at' => null,
                'paid_at' => null,
            ],
        );

        EmployeeLoan::updateOrCreate(
            ['loan_number' => 'LOAN-1001'],
            [
                'company_id' => $companies['B360D']->id,
                'employee_id' => $seededEmployees['EMP-0030']->id,
                'requested_by_user_id' => $users['amit.verma@builder360.test']->id,
                'approved_by_user_id' => null,
                'disbursed_by_user_id' => null,
                'loan_type' => 'salary_advance',
                'status' => 'submitted',
                'principal_amount' => 25000,
                'approved_amount' => 0,
                'installment_months' => 5,
                'monthly_installment' => 0,
                'requested_on' => now()->subDay()->toDateString(),
                'repayment_starts_on' => null,
                'purpose' => 'Short-term salary advance for family medical expense.',
                'decision_note' => null,
                'workflow_history' => [
                    ['status' => 'submitted', 'actor_user_id' => $users['amit.verma@builder360.test']->id, 'actor' => 'Amit Verma', 'note' => 'Salary advance request submitted', 'at' => now()->subDay()->toISOString()],
                ],
                'approved_at' => null,
                'disbursed_at' => null,
            ],
        );

        HrHelpdeskTicket::updateOrCreate(
            ['ticket_number' => 'HRT-1001'],
            [
                'company_id' => $companies['B360D']->id,
                'employee_id' => $seededEmployees['EMP-0030']->id,
                'raised_by_user_id' => $users['amit.verma@builder360.test']->id,
                'assigned_to_user_id' => $users['deepa.rao@builder360.test']->id,
                'closed_by_user_id' => null,
                'category' => 'documents',
                'priority' => 'medium',
                'status' => 'assigned',
                'subject' => 'Update nominee details in employee records',
                'description' => 'Please update nominee details and confirm once the HR record is updated.',
                'resolution_summary' => null,
                'attachments' => [],
                'workflow_history' => [
                    ['status' => 'open', 'actor_user_id' => $users['amit.verma@builder360.test']->id, 'actor' => 'Amit Verma', 'note' => 'HR helpdesk ticket opened', 'at' => now()->subHours(3)->toISOString()],
                    ['status' => 'assigned', 'actor_user_id' => $users['deepa.rao@builder360.test']->id, 'actor' => 'Deepa Rao', 'note' => 'Assigned to HR manager', 'at' => now()->subHours(2)->toISOString()],
                ],
                'resolved_at' => null,
                'closed_at' => null,
            ],
        );

        foreach ($seededEmployees as $employee) {
            foreach ($leaveTypes as $leaveType) {
                $opening = $leaveType->code === 'LOP' ? 0 : (float) $leaveType->annual_entitlement_days;

                EmployeeLeaveBalance::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'leave_type_id' => $leaveType->id,
                        'period_year' => (int) now()->year,
                    ],
                    [
                        'company_id' => $employee->company_id,
                        'opening_balance_days' => $opening,
                        'accrued_days' => 0,
                        'used_days' => 0,
                        'pending_days' => 0,
                        'adjusted_days' => 0,
                        'available_days' => $opening,
                        'ledger' => [['event' => 'seed_opening_balance', 'days' => $opening, 'at' => now()->toISOString()]],
                    ],
                );
            }
        }

        LeaveRequest::updateOrCreate(
            ['request_number' => 'LV-1001'],
            [
                'company_id' => $companies['B360D']->id,
                'employee_id' => $seededEmployees['EMP-0012']->id,
                'leave_type_id' => $leaveTypes['SL']->id,
                'requested_by_user_id' => $users['rajesh.kulkarni@builder360.test']->id,
                'decided_by_user_id' => $users['deepa.rao@builder360.test']->id,
                'status' => 'approved',
                'starts_on' => now()->addDays(7)->toDateString(),
                'ends_on' => now()->addDays(7)->toDateString(),
                'duration_unit' => 'full_day',
                'requested_days' => 1,
                'reason' => 'Approved sick leave.',
                'decision_note' => 'Approved as initial record.',
                'workflow_history' => [
                    ['status' => 'submitted', 'actor' => 'Rajesh Kulkarni', 'at' => now()->subDay()->toISOString()],
                    ['status' => 'approved', 'actor' => 'Deepa Rao', 'at' => now()->toISOString()],
                ],
                'decided_at' => now(),
            ],
        );

        EmployeeLeaveBalance::where('employee_id', $seededEmployees['EMP-0012']->id)
            ->where('leave_type_id', $leaveTypes['SL']->id)
            ->where('period_year', (int) now()->year)
            ->update([
                'used_days' => 1,
                'available_days' => 6,
                'ledger' => [
                    ['event' => 'seed_opening_balance', 'days' => 7, 'at' => now()->subDay()->toISOString()],
                    ['event' => 'seed_approved_leave', 'days' => -1, 'request_number' => 'LV-1001', 'at' => now()->toISOString()],
                ],
            ]);

        foreach ($seededEmployees as $employee) {
            SalaryAssignment::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'salary_structure_id' => $defaultSalaryStructure->id,
                    'effective_from' => now()->startOfYear()->toDateString(),
                ],
                [
                    'company_id' => $employee->company_id,
                    'effective_to' => null,
                    'status' => 'active',
                    'metadata' => ['source' => 'database_seed'],
                ],
            );

            EmployeeShiftAssignment::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'attendance_shift_id' => $attendanceShift->id,
                    'effective_from' => now()->startOfYear()->toDateString(),
                ],
                [
                    'company_id' => $employee->company_id,
                    'effective_to' => null,
                    'is_active' => true,
                ],
            );
        }

        $attendanceWorkDate = now()->subDay()->toDateString();
        $attendanceRecord = AttendanceRecord::query()
            ->where('employee_id', $seededEmployees['EMP-0021']->id)
            ->whereDate('work_date', $attendanceWorkDate)
            ->first() ?? new AttendanceRecord([
                'employee_id' => $seededEmployees['EMP-0021']->id,
                'work_date' => $attendanceWorkDate,
            ]);

        $attendanceRecord->forceFill([
            'company_id' => $companies['B360D']->id,
            'attendance_shift_id' => $attendanceShift->id,
            'check_in_at' => now()->subDay()->setTime(9, 46)->toDateTimeString(),
            'check_out_at' => now()->subDay()->setTime(18, 12)->toDateTimeString(),
            'source' => 'seeded_biometric',
            'status' => 'late',
            'late_minutes' => 6,
            'early_leave_minutes' => 8,
            'worked_minutes' => 506,
            'metadata' => ['source' => 'database_seed', 'device' => 'demo-biometric-01'],
        ])->save();
    }

    private function seedUserPassword(): string
    {
        $configuredPassword = config('builder360.demo_seed_password');

        if (is_string($configuredPassword) && $configuredPassword !== '') {
            return $configuredPassword;
        }

        if (app()->environment('production')) {
            throw new RuntimeException('BUILDER360_DEMO_PASSWORD must be configured before seeding demo users in production.');
        }

        return 'Builder360@123';
    }
}
