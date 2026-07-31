<?php
namespace App\Http\Requests\Scoring;
use Illuminate\Foundation\Http\FormRequest;
final class ActivateScoringRuleRequest extends FormRequest {
    public function authorize(): bool { return $this->user()?->can('activate', $this->route('scoringRule')) === true; }
    public function rules(): array { return []; }
}
