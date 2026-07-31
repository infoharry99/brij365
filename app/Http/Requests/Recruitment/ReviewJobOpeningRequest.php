<?php

namespace App\Http\Requests\Recruitment;

use App\Models\JobOpening;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReviewJobOpeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        $jobOpening = $this->route('jobOpening');
        $action = $this->route()?->getActionMethod();
        $ability = $action === 'rejectOpening' ? 'reject' : 'approve';

        return $jobOpening instanceof JobOpening
            && ($this->user()?->can($ability, $jobOpening) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'review_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $jobOpening = $this->route('jobOpening');

                if (! $jobOpening instanceof JobOpening) {
                    $validator->errors()->add('job_opening', 'The selected requisition is invalid.');

                    return;
                }

                if ($jobOpening->status !== 'pending_approval') {
                    $validator->errors()->add('job_opening', 'Only pending requisitions can be reviewed.');
                }
            },
        ];
    }
}
