<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class CloseHelpdeskTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('hrHelpdeskTicket');
        return $ticket instanceof \App\Models\HrHelpdeskTicket && $this->user()?->can('close', $ticket) === true;
    }

    public function rules(): array { return ['note' => ['nullable', 'string', 'max:1000']]; }
}
