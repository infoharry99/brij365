<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class ResolveHelpdeskTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('hrHelpdeskTicket');
        return $ticket instanceof \App\Models\HrHelpdeskTicket && $this->user()?->can('manage', $ticket) === true;
    }

    public function rules(): array { return ['resolution_summary' => ['required', 'string', 'min:10', 'max:5000']]; }
}
