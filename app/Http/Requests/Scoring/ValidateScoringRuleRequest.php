<?php
namespace App\Http\Requests\Scoring;
use Illuminate\Foundation\Http\FormRequest;
final class ValidateScoringRuleRequest extends FormRequest {
    public function authorize(): bool { return $this->user()?->can('validate', $this->route('scoringRule')) === true; }
    public function rules(): array { return []; }
}
