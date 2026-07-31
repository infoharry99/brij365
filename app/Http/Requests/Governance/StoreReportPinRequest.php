<?php

namespace App\Http\Requests\Governance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportPinRequest extends FormRequest
{
    private const REPORT_KEYS = [
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
        'audit_events',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        if ($user?->can('reports.view') !== true) {
            return false;
        }

        return $this->input('report_key') !== 'audit_events'
            || $user->can('audit.view') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'report_key' => ['required', 'string', Rule::in(self::REPORT_KEYS)],
            'label' => ['required', 'string', 'max:160'],
            'filters' => ['nullable', 'array'],
        ];
    }
}
