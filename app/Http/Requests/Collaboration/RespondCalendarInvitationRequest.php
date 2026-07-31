<?php
namespace App\Http\Requests\Collaboration;
use App\Models\CalendarEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
final class RespondCalendarInvitationRequest extends FormRequest {
    public function authorize(): bool { $event=$this->route('calendarEvent'); return $event instanceof CalendarEvent && ($this->user()?->can('view',$event)??false); }
    public function rules(): array { return ['response'=>['required',Rule::in(['accepted','tentative','declined'])]]; }
}
