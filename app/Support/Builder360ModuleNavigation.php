<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

class Builder360ModuleNavigation
{
    /**
     * @return array<string, string>
     */
    public static function routeMap(): array
    {
        return [
            'dashboard' => 'builder360.dashboard',
            'approvals' => 'builder360.approvals.index',
            'notifications' => 'notifications.index',
            'reports' => 'governance.report-register.index',
            'tasks' => 'collaboration.tasks.index',
            'calendar' => 'collaboration.calendar-events.index',
            'chat' => 'collaboration.chat.index',
            'mailbox' => 'mailbox.index',
            'leads' => 'crm.leads.index',
            'qualification' => 'crm.lead-qualifications.index',
            'sitevisits' => 'crm.site-visits.index',
            'sales' => 'sales.bookings.index',
            'marketing' => 'crm.campaigns.index',
            'collections' => 'finance.collections.index',
            'funnel' => 'crm.analytics.index',
            'performance' => 'crm.analytics.index',
            'projects' => 'projects.index',
            'inventory' => 'inventory.units.index',
            'pricing' => 'inventory.unit-price-versions.index',
            'cost' => 'projects.index',
            'planning' => 'construction.milestones.index',
            'progress' => 'construction.daily-progress-reports.index',
            'materials' => 'procurement.stock-items.index',
            'stock-issues' => 'procurement.stock-items.index',
            'procurement' => 'procurement.dashboard',
            'purchase-requisitions' => 'procurement.requisitions.index',
            'purchase-orders' => 'procurement.purchase-orders.index',
            'goods-receipts' => 'procurement.goods-receipts.index',
            'vendors' => 'procurement.vendors.index',
            'contractors' => 'construction.contractor-bills.index',
            'boq' => 'construction.boq-items.index',
            'measurements' => 'construction.contractor-measurements.index',
            'contractor-bills' => 'construction.contractor-bills.index',
            'hr' => 'hr.employees.index',
            'ess' => 'hr.employees.me',
            'hr-attendance' => 'hr.attendance-records.index',
            'hr-leave' => 'hr.leave-requests.index',
            'hr-performance' => 'hr.performance-cycles.index',
            'hr-confirmation' => 'hr.confirmation-cases.index',
            'hr-separation' => 'hr.separation-settlements.index',
            'hr-assets' => 'hr.assets.index',
            'hr-claims' => 'hr.expense-claims.index',
            'hr-loans' => 'hr.loans.index',
            'hr-helpdesk' => 'hr.helpdesk-tickets.index',
            'hr-documents' => 'hr.employee-documents.index',
            'hr-compliance' => 'hr.compliance-rule-settings.index',
            'payroll' => 'payroll.runs.index',
            'payroll-structures' => 'payroll.salary-structures.index',
            'payroll-components' => 'payroll.components.index',
            'payroll-bank-batches' => 'payroll.bank-transfer-batches.index',
            'payroll-commissions' => 'payroll.commission-runs.index',
            'payroll-tax-documents' => 'payroll.tax-documents.index',
            'recruitment' => 'recruitment.job-openings.index',
            'recruitment-candidates' => 'recruitment.candidates.index',
            'recruitment-interviews' => 'recruitment.interviews.index',
            'recruitment-offers' => 'recruitment.offers.index',
            'recruitment-sources' => 'recruitment.source-summary',
            'finance' => 'finance.dashboard',
            'finance-vouchers' => 'finance.vouchers.index',
            'finance-payment-requests' => 'finance.payment-requests.index',
            'finance-gst-entries' => 'finance.gst-entries.index',
            'finance-gst-returns' => 'finance.gst-return-periods.index',
            'legal' => 'legal.rera-registrations.index',
            'legal-project-approvals' => 'legal.project-approvals.index',
            'legal-obligations' => 'legal.compliance-obligations.index',
            'documents' => 'documents.index',
            'document-categories' => 'documents.categories.index',
            'possession' => 'possession.handovers.index',
            'possession-snags' => 'possession.snags.index',
            'complaints' => 'after-sales.tickets.index',
            'after-sales-work-orders' => 'after-sales.work-orders.index',
            'maintenance' => 'maintenance.societies.index',
            'maintenance-handover-items' => 'maintenance.handover-items.index',
            'maintenance-dues' => 'maintenance.dues.index',
            'buyer' => 'buyer.summary',
            'inquiry' => 'crm.prospect-inquiries.index',
            'mobile' => 'operations.readiness',
            'admin' => 'admin.users.index',
            'admin-users' => 'admin.users.index',
            'admin-roles' => 'admin.roles.index',
            'workflows' => 'settings.system-settings.index',
            'audit' => 'governance.audit-events.index',
            'auth' => 'operations.readiness',
            'settings' => 'settings.system-settings.index',
            'scoring' => 'scoring.index',
            'data-imports' => 'settings.data-imports.index',
            'partner' => 'partner.summary',
        ];
    }

    public static function routeNameFor(?string $route): ?string
    {
        if (! $route) {
            return null;
        }

        return self::routeMap()[$route] ?? null;
    }

    /**
     * @param  array<string, mixed>  $bootstrap
     */
    public static function urlFor(?string $route, array $bootstrap): ?string
    {
        if (! $route) {
            return null;
        }

        if ($route === 'buyer' && empty($bootstrap['buyer_portal'])) {
            return null;
        }

        if ($route === 'partner' && empty($bootstrap['partner_portal'])) {
            return null;
        }

        $routeName = self::routeNameFor($route);

        if (! $routeName || ! Route::has($routeName)) {
            return null;
        }

        return route($routeName);
    }

    public static function isActive(?string $route): bool
    {
        if (! $route) {
            return false;
        }

        if ($route === 'mailbox') {
            return request()->routeIs('mailbox.*') || request()->routeIs('collaboration.messages.*');
        }

        if ($route === 'chat') {
            return request()->routeIs('collaboration.chat.*');
        }

        if ($route === 'tasks') {
            return request()->routeIs('collaboration.tasks.*') || request()->routeIs('work-tasks.*');
        }

        if ($route === 'calendar') {
            return request()->routeIs('collaboration.calendar-events.*') || request()->routeIs('calendar.*');
        }

        $routeName = self::routeNameFor($route);

        if (! $routeName) {
            return false;
        }

        if (request()->routeIs($routeName)) {
            return true;
        }

        $prefix = \Illuminate\Support\Str::before($routeName, '.');

        return $prefix !== '' && request()->routeIs($prefix . '.*');
    }
}
