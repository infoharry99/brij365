<?php

namespace App\Http\Requests\AfterSales;

use App\Models\ServiceTicket;
use Illuminate\Foundation\Http\FormRequest;

class CloseServiceTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $serviceTicket = $this->route('serviceTicket');

        return $serviceTicket instanceof ServiceTicket
            && $this->user()?->can('close', $serviceTicket) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_rating' => ['nullable', 'integer', 'between:1,5'],
            'note' => ['nullable', 'string', 'max:1000'],
            'scoring_inputs' => ['nullable', 'array'],
            'scoring_inputs.resolution_time' => ['nullable', 'numeric', 'between:0,100'],
            'scoring_inputs.reopened_penalty' => ['nullable', 'numeric', 'between:0,100'],
            'scoring_inputs.escalation_impact' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }
}
