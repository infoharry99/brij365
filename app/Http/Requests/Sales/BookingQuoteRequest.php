<?php

namespace App\Http\Requests\Sales;

use App\Models\ProjectUnit;
use App\Services\Security\CompanyScopeService;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BookingQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('booking.view') === true
            || $this->user()?->can('booking.manage') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_unit_id' => ['required', 'integer', 'exists:project_units,id'],
            'quoted_on' => ['nullable', 'date'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->enterpriseAmountMaxRule()],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();
            $unit = $this->filled('project_unit_id')
                ? ProjectUnit::query()->whereKey($this->integer('project_unit_id'))->first()
                : null;

            if (! $user || ! $unit) {
                return;
            }

            if (! app(CompanyScopeService::class)->allows($user, $unit->company_id)) {
                $validator->errors()->add('project_unit_id', 'The selected unit is not available for your company.');
            }
        });
    }
}
