<?php

namespace App\Http\Requests\Crm;

use App\Models\ProspectInquiry;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignProspectInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ProspectInquiry|null $prospectInquiry */
        $prospectInquiry = $this->route('prospectInquiry');

        return $prospectInquiry instanceof ProspectInquiry
            && $this->user()?->can('update', $prospectInquiry) === true;
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
                /** @var ProspectInquiry|null $prospectInquiry */
                $prospectInquiry = $this->route('prospectInquiry');
                $user = $this->user();

                if (! $prospectInquiry instanceof ProspectInquiry || ! $user || ! $this->filled('assigned_to_user_id')) {
                    return;
                }

                $assignee = User::query()->whereKey($this->integer('assigned_to_user_id'))->first();

                if (! $assignee || $assignee->status !== 'active') {
                    $validator->errors()->add('assigned_to_user_id', 'The selected assignee must be an active user.');

                    return;
                }

                if (! app(CompanyScopeService::class)->allows($user, $assignee->company_id) || (int) $assignee->company_id !== (int) $prospectInquiry->company_id) {
                    $validator->errors()->add('assigned_to_user_id', 'The selected assignee must belong to the inquiry company.');
                }

                if (! ($assignee->hasPermission('crm.manage') || $assignee->hasPermission('crm.view'))) {
                    $validator->errors()->add('assigned_to_user_id', 'The selected assignee must have CRM access.');
                }
            },
        ];
    }
}
