<?php

namespace App\Http\Requests\Sales;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Partner;
use App\Models\ProjectUnit;
use App\Services\Security\CompanyScopeService;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Booking::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_unit_id' => ['required', 'integer', 'exists:project_units,id'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'booking_amount' => ['required', 'numeric', 'min:0', app(MoneyInputPolicy::class)->enterpriseAmountMaxRule()],
            'discount_amount' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->enterpriseAmountMaxRule()],
            'booked_on' => ['nullable', 'date'],
            'payment_schedule' => ['nullable', 'array', 'max:20'],
            'payment_schedule.*.milestone' => ['required_with:payment_schedule', 'string', 'max:120'],
            'payment_schedule.*.sequence' => ['required_with:payment_schedule', 'integer', 'min:1', 'max:99'],
            'payment_schedule.*.percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment_schedule.*.amount' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->enterpriseAmountMaxRule()],
            'payment_schedule.*.due_on' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $unit = ProjectUnit::with('project')->find($this->integer('project_unit_id'));
            $customer = Customer::find($this->integer('customer_id'));
            $lead = $this->integer('lead_id') ? Lead::find($this->integer('lead_id')) : null;
            $partner = $this->integer('partner_id') ? Partner::find($this->integer('partner_id')) : null;

            if (! $unit || ! $customer) {
                return;
            }

            $user = $this->user();
            if (! $user || ! app(CompanyScopeService::class)->allows($user, $unit->company_id)) {
                $validator->errors()->add('project_unit_id', 'The selected unit is outside your company scope.');
            }

            if (! $unit->isBookable()) {
                $validator->errors()->add('project_unit_id', 'The selected unit is not available for booking.');
            }

            if ($lead && $lead->project_id !== null && $lead->project_id !== $unit->project_id) {
                $validator->errors()->add('lead_id', 'The selected lead belongs to a different project.');
            }

            if ($lead && $lead->customer_id !== $customer->id) {
                $validator->errors()->add('customer_id', 'The selected customer does not match the selected lead.');
            }

            if ($lead && $partner && $lead->partner_id !== null && $lead->partner_id !== $partner->id) {
                $validator->errors()->add('partner_id', 'The selected partner does not match the selected lead.');
            }

            $schedule = collect($this->input('payment_schedule', []));
            if ($schedule->pluck('sequence')->duplicates()->isNotEmpty()) {
                $validator->errors()->add('payment_schedule', 'Payment schedule sequence values must be unique.');
            }
        });
    }
}
