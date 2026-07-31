<?php

namespace App\Http\Requests\AfterSales;

use App\Models\MaintenanceWorkOrder;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Security\CompanyScopeService;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMaintenanceWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MaintenanceWorkOrder::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_ticket_id' => ['required', 'integer', Rule::exists('service_tickets', 'id')],
            'assigned_to_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'vendor_id' => ['nullable', 'integer', Rule::exists('vendors', 'id')],
            'scheduled_on' => ['nullable', 'date'],
            'scope_of_work' => ['required', 'string', 'min:10', 'max:5000'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->maintenanceCostMaxRule()],
            'materials_required' => ['nullable', 'array', 'max:50'],
            'materials_required.*.item' => ['required_with:materials_required', 'string', 'max:255'],
            'materials_required.*.quantity' => ['required_with:materials_required', 'numeric', 'min:0.01'],
            'materials_required.*.uom' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $ticket = ServiceTicket::query()
                    ->whereKey($this->integer('service_ticket_id'))
                    ->first();

                if (! $ticket) {
                    return;
                }

                $user = $this->user();

                if (! $user || ! app(CompanyScopeService::class)->allows($user, $ticket->company_id)) {
                    $validator->errors()->add('service_ticket_id', 'The selected ticket is not available for your company.');

                    return;
                }

                if ($this->filled('assigned_to_user_id')) {
                    $assigneeCompanyId = User::query()
                        ->whereKey($this->integer('assigned_to_user_id'))
                        ->value('company_id');

                    if ((int) $assigneeCompanyId !== (int) $ticket->company_id) {
                        $validator->errors()->add('assigned_to_user_id', 'The assignee must belong to the ticket company.');
                    }
                }

                if ($this->filled('vendor_id')) {
                    $vendor = Vendor::query()
                        ->whereKey($this->integer('vendor_id'))
                        ->first();

                    if (! $vendor || (int) $vendor->company_id !== (int) $ticket->company_id || $vendor->status !== 'active') {
                        $validator->errors()->add('vendor_id', 'The selected vendor is not active for the ticket company.');
                    }
                }
            },
        ];
    }
}