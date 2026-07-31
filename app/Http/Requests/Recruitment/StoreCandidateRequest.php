<?php

namespace App\Http\Requests\Recruitment;

use App\Models\Candidate;
use App\Models\JobOpening;
use App\Services\Security\CompanyScopeService;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Candidate::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'job_opening_id' => ['required', 'integer', Rule::exists('job_openings', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'source' => ['required', 'string', 'max:120'],
            'current_company' => ['nullable', 'string', 'max:255'],
            'experience_years' => ['required', 'numeric', 'min:0', 'max:60'],
            'current_ctc' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->ctcAmountMaxRule()],
            'expected_ctc' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->ctcAmountMaxRule()],
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'skills' => ['nullable', 'array', 'max:30'],
            'skills.*' => ['string', 'max:80'],
            'documents' => ['nullable', 'array', 'max:20'],
            'documents.*.type' => ['required_with:documents', 'string', 'max:80'],
            'documents.*.name' => ['required_with:documents', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();
                $opening = JobOpening::query()->whereKey($this->integer('job_opening_id'))->first();

                if (
                    ! $opening
                    || ! $user
                    || ! app(CompanyScopeService::class)->allows($user, $opening->company_id)
                    || $opening->status !== 'open'
                ) {
                    $validator->errors()->add('job_opening_id', 'The selected job opening is not open for your company.');

                    return;
                }

                $companyId = $opening->company_id;

                if (Candidate::query()->where('company_id', $companyId)->where('email', $this->string('email')->toString())->exists()) {
                    $validator->errors()->add('email', 'A candidate with this email already exists for this company.');
                }

                if (Candidate::query()->where('company_id', $companyId)->where('phone', $this->string('phone')->toString())->exists()) {
                    $validator->errors()->add('phone', 'A candidate with this phone number already exists for this company.');
                }
            },
        ];
    }
}