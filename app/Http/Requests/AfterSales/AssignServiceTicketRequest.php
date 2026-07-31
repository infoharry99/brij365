<?php

namespace App\Http\Requests\AfterSales;

use App\Models\ServiceTicket;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignServiceTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $serviceTicket = $this->route('serviceTicket');

        return $serviceTicket instanceof ServiceTicket
            && $this->user()?->can('assign', $serviceTicket) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assigned_to_user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $ticket = $this->route('serviceTicket');

                if (! $ticket instanceof ServiceTicket) {
                    return;
                }

                $assigneeCompanyId = User::query()
                    ->whereKey($this->integer('assigned_to_user_id'))
                    ->value('company_id');

                if ((int) $assigneeCompanyId !== (int) $ticket->company_id) {
                    $validator->errors()->add('assigned_to_user_id', 'The assignee must belong to the ticket company.');
                }
            },
        ];
    }
}
