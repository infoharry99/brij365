<?php

namespace App\Http\Requests\Recruitment;

use App\Models\Candidate;
use App\Models\JobOffer;
use App\Services\Security\CompanyScopeService;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreJobOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', JobOffer::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'candidate_id' => ['required', 'integer', Rule::exists('candidates', 'id')],
            'template_code' => ['required', 'string', 'max:80'],
            'offered_ctc' => ['required', 'numeric', 'min:1', app(MoneyInputPolicy::class)->ctcAmountMaxRule()],
            'joining_date' => ['required', 'date', 'after:today'],
            'placeholders' => ['required', 'array'],
            'placeholders.candidate_name' => ['required', 'string', 'max:255'],
            'placeholders.designation' => ['required', 'string', 'max:255'],
            'placeholders.department' => ['required', 'string', 'max:255'],
            'placeholders.joining_date' => ['required', 'date'],
            'placeholders.offered_ctc' => ['required', 'numeric', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();
                $candidate = Candidate::query()->whereKey($this->integer('candidate_id'))->first();

                if (
                    ! $candidate
                    || ! $user
                    || ! app(CompanyScopeService::class)->allows($user, $candidate->company_id)
                    || $candidate->status !== 'active'
                ) {
                    $validator->errors()->add('candidate_id', 'The selected candidate is not active for your company.');

                    return;
                }

                $companyId = $candidate->company_id;

                if (! in_array($candidate->stage, ['interview_scheduled', 'interviewed', 'selected', 'offer_draft'], true)) {
                    $validator->errors()->add('candidate_id', 'An offer can be created only after interview scheduling or selection.');
                }

                if (JobOffer::query()->where('company_id', $companyId)->where('candidate_id', $candidate->id)->whereIn('status', ['draft', 'released', 'accepted'])->exists()) {
                    $validator->errors()->add('candidate_id', 'An active offer already exists for this candidate.');
                }
            },
        ];
    }
}