<?php

namespace App\Http\Requests\Hr;

use App\Domain\Hr\Services\HrHelpdeskAssigneeCandidates;
use App\Models\HrHelpdeskTicket;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignHelpdeskTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('hrHelpdeskTicket');
        return $ticket instanceof HrHelpdeskTicket && $this->user()?->can('manage', $ticket) === true;
    }

    public function rules(): array
    {
        return ['assigned_to_user_id' => ['required', 'integer', Rule::exists('users', 'id')], 'note' => ['nullable', 'string', 'max:1000']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $ticket = $this->route('hrHelpdeskTicket');
            $assignee = User::find($this->integer('assigned_to_user_id'));

            if ($ticket instanceof HrHelpdeskTicket && $assignee && $this->user() && ! app(HrHelpdeskAssigneeCandidates::class)->isEligible($this->user(), $ticket, $assignee)) {
                $validator->errors()->add('assigned_to_user_id', 'The selected assignee must be an active internal user in the ticket company.');
            }
        }];
    }
}
