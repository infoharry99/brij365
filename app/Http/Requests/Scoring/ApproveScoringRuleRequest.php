<?php
namespace App\Http\Requests\Scoring;
use Illuminate\Foundation\Http\FormRequest;
final class ApproveScoringRuleRequest extends FormRequest {
    public function authorize(): bool { return $this->user()?->can('approve', $this->route('scoringRule')) === true; }
    public function rules(): array { return []; }
}
