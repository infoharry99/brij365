<?php

namespace App\Http\Requests\Crm;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();
        $lead = $this->route('lead');

        return $lead instanceof Lead
            && $user?->can('update', $lead) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stage' => ['required', 'string', 'in:New,Qualified,Site Visit Planned,Negotiation,Booked,Lost'],
            'status' => ['nullable', 'string', 'in:open,won,lost,on_hold'],
            'follow_up_at' => ['nullable', 'date'],
        ];
    }
}
