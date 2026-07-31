<?php
namespace App\Http\Requests\Collaboration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
final class RespondGuestCalendarInvitationRequest extends FormRequest {
    public function authorize(): bool { return $this->hasValidSignature(); }
    public function rules(): array { return ['response'=>['required',Rule::in(['accepted','tentative','declined'])]]; }
}
