<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->modules() as $index => $module) {
            DB::table('erp_modules')->updateOrInsert(
                ['slug' => $module['slug']],
                $module + [
                    'sort_order' => ($index + 1) * 10,
                    'required_permissions' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('erp_modules')
            ->whereIn('slug', collect($this->modules())->pluck('slug')->all())
            ->whereNotIn('slug', [
                'dashboard',
                'leads',
                'qualification',
                'sitevisits',
                'sales',
                'projects',
                'planning',
                'hr',
                'finance',
                'after-sales',
                'tasks',
                'calendar',
                'notifications',
                'settings',
                'administration',
                'audit',
                'partner',
            ])
            ->delete();

        DB::table('erp_modules')->where('slug', 'after-sales')->update([
            'name' => 'After-Sales & Maintenance',
            'route' => 'after-sales',
            'icon' => 'headphones',
            'group_name' => 'Customer Service',
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function modules(): array
    {
        return [
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
            ['group_name' => 'Construction', 'slug' => 'procurement', 'name' => 'Procurement', 'route' => 'procurement', 'icon' => 'cart'],
            ['group_name' => 'Construction', 'slug' => 'vendors', 'name' => 'Vendor Management', 'route' => 'vendors', 'icon' => 'truck'],
            ['group_name' => 'Construction', 'slug' => 'contractors', 'name' => 'Contractor Mgmt', 'route' => 'contractors', 'icon' => 'wrench'],
            ['group_name' => 'Construction', 'slug' => 'boq', 'name' => 'BOQ / Measurement', 'route' => 'boq', 'icon' => 'ruler'],
            ['group_name' => 'People', 'slug' => 'hr', 'name' => 'HR & Employees', 'route' => 'hr', 'icon' => 'id'],
            ['group_name' => 'People', 'slug' => 'ess', 'name' => 'Employee Self-Service', 'route' => 'ess', 'icon' => 'user'],
            ['group_name' => 'People', 'slug' => 'payroll', 'name' => 'Payroll Admin', 'route' => 'payroll', 'icon' => 'wallet'],
            ['group_name' => 'People', 'slug' => 'recruitment', 'name' => 'Recruitment', 'route' => 'recruitment', 'icon' => 'funnel'],
            ['group_name' => 'Operations', 'slug' => 'finance', 'name' => 'Accounts & Finance', 'route' => 'finance', 'icon' => 'wallet'],
            ['group_name' => 'Operations', 'slug' => 'legal', 'name' => 'Legal / RERA', 'route' => 'legal', 'icon' => 'shield'],
            ['group_name' => 'Operations', 'slug' => 'documents', 'name' => 'Document Mgmt', 'route' => 'documents', 'icon' => 'folder'],
            ['group_name' => 'Operations', 'slug' => 'possession', 'name' => 'Possession & Handover', 'route' => 'possession', 'icon' => 'key'],
            ['group_name' => 'Operations', 'slug' => 'after-sales', 'name' => 'After-Sales', 'route' => 'complaints', 'icon' => 'headset'],
            ['group_name' => 'Operations', 'slug' => 'maintenance', 'name' => 'Maintenance & Society', 'route' => 'maintenance', 'icon' => 'wrench'],
            ['group_name' => 'Customer', 'slug' => 'buyer', 'name' => 'Buyer Portal', 'route' => 'buyer', 'icon' => 'home'],
            ['group_name' => 'Customer', 'slug' => 'inquiry', 'name' => 'Prospect Inquiry', 'route' => 'inquiry', 'icon' => 'globe'],
            ['group_name' => 'Customer', 'slug' => 'mobile', 'name' => 'Mobile Apps', 'route' => 'mobile', 'icon' => 'phone'],
            ['group_name' => 'System', 'slug' => 'administration', 'name' => 'Administration', 'route' => 'admin', 'icon' => 'sliders'],
            ['group_name' => 'System', 'slug' => 'workflows', 'name' => 'Business Workflows', 'route' => 'workflows', 'icon' => 'funnel'],
            ['group_name' => 'System', 'slug' => 'audit', 'name' => 'Audit Trail', 'route' => 'audit', 'icon' => 'eye'],
            ['group_name' => 'System', 'slug' => 'auth', 'name' => 'Authentication', 'route' => 'auth', 'icon' => 'shield'],
            ['group_name' => 'System', 'slug' => 'settings', 'name' => 'System Settings', 'route' => 'settings', 'icon' => 'gear'],
            ['group_name' => 'Partner Portal', 'slug' => 'partner', 'name' => 'Partner Dashboard', 'route' => 'partner', 'icon' => 'grid'],
        ];
    }
};
