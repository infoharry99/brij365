<?php

namespace App\Http\Requests\Hr;

use App\Http\Requests\Hr\Concerns\ValidatesPerformanceReviewVersion;
use App\Models\PerformanceReview;
use Illuminate\Foundation\Http\FormRequest;

class StorePerformanceScoreOverrideRequest extends FormRequest
{
    use ValidatesPerformanceReviewVersion;

    public function authorize(): bool
    {
        $review = $this->route('performanceReview');

        return $review instanceof PerformanceReview && $this->user()?->can('requestOverride', $review) === true;
    }

    public function rules(): array
    {
        $review = $this->route('performanceReview');

        return [
            'lock_version' => $this->performanceReviewVersionRules($review instanceof PerformanceReview ? $review : null),
            'requested_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'reason' => ['required', 'string', 'min:12', 'max:2000'],
            'evidence' => ['required', 'string', 'min:12', 'max:3000'],
        ];
    }
}
