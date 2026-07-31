<?php

namespace App\Domain\Governance\Services;

final class ReportRegisterCatalog
{
    /** @return array<string, string> */
    public function reports(bool $includeAudit): array
    {
        $reports = [
            'bookings' => 'Bookings',
            'collections' => 'Collections',
            'payroll' => 'Payroll',
            'service_tickets' => 'Service Tickets',
            'leads' => 'Leads',
            'inventory_units' => 'Unit Inventory',
            'stock_items' => 'Stock Items',
            'stock_movements' => 'Stock Movements',
            'purchase_orders' => 'Purchase Orders',
            'vendors' => 'Vendors',
            'construction_milestones' => 'Construction Milestones',
            'daily_progress_reports' => 'Daily Progress Reports',
            'rera_registrations' => 'RERA Registrations',
        ];

        return $includeAudit ? [...$reports, 'audit_events' => 'Activity History'] : $reports;
    }
}
