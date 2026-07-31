<?php

namespace App\Http\Requests\Maintenance;

use App\Models\Booking;
use App\Models\MaintenanceDue;
use App\Services\Security\CompanyScopeService;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMaintenanceDueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MaintenanceDue::class) === true;
    }

    public function rules(): array
    {
        return [
            'booking_id' => ['required', 'integer', Rule::exists('bookings', 'id')],
            'period_start_on' => ['required', 'date'],
            'period_end_on' => ['required', 'date', 'after_or_equal:period_start_on'],
            'due_on' => ['required', 'date', 'after_or_equal:period_start_on'],
            'amount' => ['required', 'numeric', 'min:1', app(MoneyInputPolicy::class)->maintenanceCostMaxRule()],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $booking = Booking::query()->whereKey($this->integer('booking_id'))->first();
                $user = $this->user();

                if (! $booking || ! $user || ! app(CompanyScopeService::class)->allows($user, $booking->company_id)) {
                    $validator->errors()->add('booking_id', 'The selected booking is outside your company scope.');

                    return;
                }

                if (! in_array($booking->status, ['confirmed', 'agreement_pending', 'registered'], true)) {
                    $validator->errors()->add('booking_id', 'Maintenance dues can only be raised for active bookings.');
                }

                if ($booking->project_unit_id === null) {
                    $validator->errors()->add('booking_id', 'The selected booking must have a unit.');
                }

                $duplicate = MaintenanceDue::query()
                    ->where('project_unit_id', $booking->project_unit_id)
                    ->whereDate('period_start_on', $this->date('period_start_on')?->toDateString())
                    ->whereDate('period_end_on', $this->date('period_end_on')?->toDateString())
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add('period_start_on', 'A maintenance due already exists for this unit and period.');
                }
            },
        ];
    }
}
