<?php

namespace App\Http\Requests\Hr;

use App\Http\Requests\Hr\Concerns\ValidatesPerformanceReviewVersion;
use App\Models\PerformanceScoreOverrideRequest;
use Illuminate\Foundation\Http\FormRequest;

class DecidePerformanceScoreOverrideRequest extends FormRequest
{
    use ValidatesPerformanceReviewVersion;

    public function authorize(): bool
    {
        $override = $this->route('performanceScoreOverrideRequest');

        return $override instanceof PerformanceScoreOverrideRequest
            && (int) $override->requested_by_user_id !== (int) $this->user()?->id
            && $this->user()?->can('approveOverride', $override->review) === true;
    }

    public function rules(): array
    {
        $override = $this->route('performanceScoreOverrideRequest');
        $review = $override instanceof PerformanceScoreOverrideRequest ? $override->review : null;

        return [
            'lock_version' => $this->performanceReviewVersionRules($review),
            'decision_reason' => ['required', 'string', 'min:12', 'max:2000'],
        ];
    }
}
