<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('erp_modules')
            ->whereIn('slug', $this->expandedSidebarSlugs())
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('erp_modules')
            ->whereIn('slug', $this->expandedSidebarSlugs())
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private function expandedSidebarSlugs(): array
    {
        return [
            'stock-issues',
            'purchase-requisitions',
            'purchase-orders',
            'goods-receipts',
            'measurements',
            'contractor-bills',
            'hr-attendance',
            'hr-leave',
            'hr-performance',
            'hr-confirmation',
            'hr-separation',
            'hr-assets',
            'hr-claims',
            'hr-loans',
            'hr-helpdesk',
            'hr-documents',
            'hr-compliance',
            'payroll-structures',
            'payroll-components',
            'payroll-bank-batches',
            'payroll-commissions',
            'payroll-tax-documents',
            'recruitment-candidates',
            'recruitment-interviews',
            'recruitment-offers',
            'recruitment-sources',
            'finance-vouchers',
            'finance-payment-requests',
            'finance-gst-entries',
            'finance-gst-returns',
            'legal-project-approvals',
            'legal-obligations',
            'document-categories',
            'possession-snags',
            'after-sales-work-orders',
            'maintenance-handover-items',
            'maintenance-dues',
            'admin-users',
            'admin-roles',
            'data-imports',
        ];
    }
};
