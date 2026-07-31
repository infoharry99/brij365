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
                [
                    'group_name' => $module['group_name'],
                    'name' => $module['name'],
                    'route' => $module['route'],
                    'icon' => $module['icon'],
                    'sort_order' => 200 + $index,
                    'is_active' => false,
                    'required_permissions' => json_encode([]),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('erp_modules')
            ->whereIn('slug', array_column($this->modules(), 'slug'))
            ->delete();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function modules(): array
    {
        return [
            ['group_name' => 'Construction', 'slug' => 'stock-issues', 'name' => 'Stock Issue / Transfer', 'route' => 'stock-issues', 'icon' => 'shuffle'],
            ['group_name' => 'Construction', 'slug' => 'purchase-requisitions', 'name' => 'Purchase Requisitions', 'route' => 'purchase-requisitions', 'icon' => 'file'],
            ['group_name' => 'Construction', 'slug' => 'purchase-orders', 'name' => 'Purchase Orders', 'route' => 'purchase-orders', 'icon' => 'cart'],
            ['group_name' => 'Construction', 'slug' => 'goods-receipts', 'name' => 'Goods Receipts', 'route' => 'goods-receipts', 'icon' => 'truck'],
            ['group_name' => 'Construction', 'slug' => 'measurements', 'name' => 'Measurement Book', 'route' => 'measurements', 'icon' => 'ruler'],
            ['group_name' => 'Construction', 'slug' => 'contractor-bills', 'name' => 'Contractor Bills', 'route' => 'contractor-bills', 'icon' => 'receipt'],
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
            ['group_name' => 'People', 'slug' => 'payroll-structures', 'name' => 'Salary Structures', 'route' => 'payroll-structures', 'icon' => 'layers'],
            ['group_name' => 'People', 'slug' => 'payroll-components', 'name' => 'Payroll Components', 'route' => 'payroll-components', 'icon' => 'sliders'],
            ['group_name' => 'People', 'slug' => 'payroll-bank-batches', 'name' => 'Bank Transfer Batches', 'route' => 'payroll-bank-batches', 'icon' => 'bank'],
            ['group_name' => 'People', 'slug' => 'payroll-commissions', 'name' => 'Commission Runs', 'route' => 'payroll-commissions', 'icon' => 'rupee'],
            ['group_name' => 'People', 'slug' => 'payroll-tax-documents', 'name' => 'Tax Documents / Form 16', 'route' => 'payroll-tax-documents', 'icon' => 'file'],
            ['group_name' => 'People', 'slug' => 'recruitment-candidates', 'name' => 'Candidate Master', 'route' => 'recruitment-candidates', 'icon' => 'users'],
            ['group_name' => 'People', 'slug' => 'recruitment-interviews', 'name' => 'Interview Scheduler', 'route' => 'recruitment-interviews', 'icon' => 'calendar'],
            ['group_name' => 'People', 'slug' => 'recruitment-offers', 'name' => 'Offer Letters', 'route' => 'recruitment-offers', 'icon' => 'file'],
            ['group_name' => 'People', 'slug' => 'recruitment-sources', 'name' => 'Recruitment Sources', 'route' => 'recruitment-sources', 'icon' => 'chart'],
            ['group_name' => 'Operations', 'slug' => 'finance-vouchers', 'name' => 'Vouchers', 'route' => 'finance-vouchers', 'icon' => 'receipt'],
            ['group_name' => 'Operations', 'slug' => 'finance-payment-requests', 'name' => 'Payment Requests', 'route' => 'finance-payment-requests', 'icon' => 'link'],
            ['group_name' => 'Operations', 'slug' => 'finance-gst-entries', 'name' => 'GST Entries', 'route' => 'finance-gst-entries', 'icon' => 'file'],
            ['group_name' => 'Operations', 'slug' => 'finance-gst-returns', 'name' => 'GST Return Periods', 'route' => 'finance-gst-returns', 'icon' => 'calendar'],
            ['group_name' => 'Operations', 'slug' => 'legal-project-approvals', 'name' => 'Project Approvals', 'route' => 'legal-project-approvals', 'icon' => 'check'],
            ['group_name' => 'Operations', 'slug' => 'legal-obligations', 'name' => 'Compliance Obligations', 'route' => 'legal-obligations', 'icon' => 'calendar'],
            ['group_name' => 'Operations', 'slug' => 'document-categories', 'name' => 'Document Categories', 'route' => 'document-categories', 'icon' => 'folder'],
            ['group_name' => 'Operations', 'slug' => 'possession-snags', 'name' => 'Snag List', 'route' => 'possession-snags', 'icon' => 'alert'],
            ['group_name' => 'Operations', 'slug' => 'after-sales-work-orders', 'name' => 'Service Work Orders', 'route' => 'after-sales-work-orders', 'icon' => 'wrench'],
            ['group_name' => 'Operations', 'slug' => 'maintenance-handover-items', 'name' => 'Common Area Handover', 'route' => 'maintenance-handover-items', 'icon' => 'check'],
            ['group_name' => 'Operations', 'slug' => 'maintenance-dues', 'name' => 'Maintenance Dues', 'route' => 'maintenance-dues', 'icon' => 'rupee'],
            ['group_name' => 'System', 'slug' => 'admin-users', 'name' => 'User Management', 'route' => 'admin-users', 'icon' => 'users'],
            ['group_name' => 'System', 'slug' => 'admin-roles', 'name' => 'Role Management', 'route' => 'admin-roles', 'icon' => 'key'],
            ['group_name' => 'System', 'slug' => 'data-imports', 'name' => 'Data Imports', 'route' => 'data-imports', 'icon' => 'upload'],
        ];
    }
};
