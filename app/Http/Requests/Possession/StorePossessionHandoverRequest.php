<?php

namespace App\Http\Requests\Possession;

use App\Models\Booking;
use App\Models\PossessionHandover;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePossessionHandoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PossessionHandover::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'booking_id' => ['required', 'integer', Rule::exists('bookings', 'id')],
            'target_handover_on' => ['nullable', 'date'],
            'checklist' => ['nullable', 'array', 'max:50'],
            'checklist.*.code' => ['required_with:checklist', 'string', 'max:80'],
            'checklist.*.label' => ['required_with:checklist', 'string', 'max:255'],
            'checklist.*.required' => ['required_with:checklist', 'boolean'],
            'checklist.*.completed' => ['required_with:checklist', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $booking = Booking::query()->whereKey($this->integer('booking_id'))->first();
                $user = $this->user();

                if (
                    ! $booking
                    || ! $user
                    || ! app(CompanyScopeService::class)->allows($user, $booking->company_id)
                    || $booking->status !== 'confirmed'
                ) {
                    $validator->errors()->add('booking_id', 'The selected confirmed booking is not available for your company.');
                }

                if (PossessionHandover::query()->where('booking_id', $this->integer('booking_id'))->exists()) {
                    $validator->errors()->add('booking_id', 'A possession handover already exists for this booking.');
                }
            },
        ];
    }
}
