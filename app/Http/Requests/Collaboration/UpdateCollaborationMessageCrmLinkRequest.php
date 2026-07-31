<?php

namespace App\Http\Requests\Collaboration;

use App\Models\Booking;
use App\Models\CollaborationMessage;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCollaborationMessageCrmLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $message = $this->route('collaborationMessage');

        return $message instanceof CollaborationMessage
            && ($this->user()?->can('linkCrm', $message) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['link', 'unlink'])],
            'record_type' => ['required_if:action,link', 'nullable', 'string', Rule::in(['project', 'lead', 'booking', 'customer'])],
            'record_id' => ['required_if:action,link', 'nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $actor = $this->user();
                $message = $this->route('collaborationMessage');

                if (! $actor || ! $message instanceof CollaborationMessage || $validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->input('action') === 'unlink') {
                    return;
                }

                $record = $this->crmRecord((string) $this->input('record_type'), (int) $this->input('record_id'));

                if (! $record) {
                    $validator->errors()->add('record_id', 'The selected CRM record was not found.');

                    return;
                }

                $companyId = $this->companyIdForCrmRecord($record);

                if (! $companyId) {
                    $validator->errors()->add('record_id', 'The selected CRM record is not linked to a Builder360 company.');

                    return;
                }

                if ((int) $message->company_id !== (int) $companyId) {
                    $validator->errors()->add('record_id', 'The selected CRM record must belong to the same company as the mailbox message.');

                    return;
                }

                if (! app(CompanyScopeService::class)->allows($actor, $companyId)) {
                    $validator->errors()->add('record_id', 'The selected CRM record is outside your company scope.');
                }
            },
        ];
    }

    private function crmRecord(string $type, int $id): ?Model
    {
        return match ($type) {
            'project' => Project::query()->whereKey($id)->first(),
            'lead' => Lead::query()->whereKey($id)->first(),
            'booking' => Booking::query()->whereKey($id)->first(),
            'customer' => Customer::query()
                ->whereKey($id)
                ->where(function ($query): void {
                    $query->whereHas('leads')
                        ->orWhereHas('bookings');
                })
                ->first(),
            default => null,
        };
    }

    private function companyIdForCrmRecord(Model $record): ?int
    {
        if ($record instanceof Customer) {
            $companyId = $record->bookings()->value('company_id')
                ?? $record->leads()->value('company_id');

            return $companyId ? (int) $companyId : null;
        }

        $companyId = $record->getAttribute('company_id');

        return $companyId ? (int) $companyId : null;
    }
}
