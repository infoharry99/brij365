<?php
namespace App\Http\Requests\Scoring;
use Illuminate\Foundation\Http\FormRequest;
final class SubmitScoringRuleRequest extends FormRequest {
    public function authorize(): bool { return $this->user()?->can('submit', $this->route('scoringRule')) === true; }
    public function rules(): array { return []; }
}
