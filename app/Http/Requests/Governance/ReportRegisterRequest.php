<?php

namespace App\Http\Requests\Governance;

use App\Models\Project;
use App\Services\Governance\ReportLimitPolicy;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReportRegisterRequest extends FormRequest
{
    /**
     * @var array<string, array<int, string>>
     */
    private const REPORT_STATUSES = [
        'bookings' => ['draft', 'confirmed', 'agreement_pending', 'registered', 'cancelled'],
        'collections' => ['submitted', 'approved', 'rejected', 'cancelled'],
        'payroll' => ['draft', 'generated', 'approved', 'rejected'],
        'service_tickets' => ['open', 'assigned', 'in_progress', 'resolved', 'closed'],
        'leads' => ['open', 'won', 'lost', 'on_hold'],
        'inventory_units' => ['available', 'reserved', 'booked', 'registered', 'handed_over', 'blocked', 'on_hold'],
        'stock_items' => ['active', 'inactive'],
        'stock_movements' => ['inward', 'issue', 'consumption', 'wastage', 'return', 'transfer_out', 'transfer_in'],
        'purchase_orders' => ['draft', 'approved', 'partially_received', 'received', 'cancelled'],
        'vendors' => ['active', 'inactive', 'blocked'],
        'construction_milestones' => ['planned', 'in_progress', 'completed', 'delayed'],
        'daily_progress_reports' => ['submitted', 'approved', 'rejected'],
        'rera_registrations' => ['submitted', 'verified'],
    ];

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

        return $this->input('report') !== 'audit_events'
            || $user->can('audit.view') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'report' => ['nullable', 'string', Rule::in(self::REPORT_KEYS)],
            'format' => ['nullable', 'string', Rule::in(['json', 'csv', 'excel', 'xls', 'pdf'])],
            'status' => ['nullable', 'string', 'max:40'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $report = (string) $this->input('report', 'bookings');

                if (
                    ! $validator->errors()->has('report')
                    && ! $validator->errors()->has('status')
                    && $this->filled('status')
                ) {
                    $allowedStatuses = self::REPORT_STATUSES[$report] ?? [];
                    $status = (string) $this->input('status');

                    if ($allowedStatuses === []) {
                        return;
                    }

                    if (! in_array($status, $allowedStatuses, true)) {
                        $validator->errors()->add(
                            'status',
                            'The selected status is not valid for the '.$report.' report.',
                        );
                    }
                }

                if (
                    ! $validator->errors()->has('report')
                    && ! $validator->errors()->has('project_id')
                    && in_array($report, ['payroll', 'audit_events', 'vendors'], true)
                    && $this->filled('project_id')
                ) {
                    $validator->errors()->add('project_id', 'Project filtering is not available for the '.$report.' report.');
                }

                if (! $validator->errors()->has('project_id') && $this->filled('project_id')) {
                    $project = Project::find($this->integer('project_id'));
                    $user = $this->user();

                    if ($project && (! $user || ! app(CompanyScopeService::class)->allows($user, $project->company_id))) {
                        $validator->errors()->add('project_id', 'The selected project is outside your company scope.');
                    }
                }

                if (
                    $validator->errors()->has('date_from')
                    || $validator->errors()->has('date_to')
                    || ! $this->filled('date_from')
                    || ! $this->filled('date_to')
                ) {
                    return;
                }

                $from = Carbon::parse((string) $this->input('date_from'))->startOfDay();
                $to = Carbon::parse((string) $this->input('date_to'))->startOfDay();
                $maxDays = app(ReportLimitPolicy::class)->maxDateRangeDays();

                if ($from->diffInDays($to) > $maxDays) {
                    $validator->errors()->add(
                        'date_to',
                        'The report date range may not exceed '.$maxDays.' days.',
                    );
                }
            },
        ];
    }

}
