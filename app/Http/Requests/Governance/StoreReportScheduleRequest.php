<?php

namespace App\Http\Requests\Governance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReportScheduleRequest extends FormRequest
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
            'frequency' => ['required', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
            'format' => ['required', 'string', Rule::in(['csv', 'excel', 'pdf'])],
            'filters' => ['nullable', 'array'],
            'recipients' => ['required', 'array', 'min:1', 'max:10'],
            'recipients.*' => ['required', 'email:rfc', 'max:255'],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $recipients = $this->collect('recipients')->map(fn ($email) => strtolower(trim((string) $email)))->all();

                if (count($recipients) !== count(array_unique($recipients))) {
                    $validator->errors()->add('recipients', 'Report schedule recipients must be unique.');
                }
            },
        ];
    }
}
