<?php
namespace App\Http\Requests\Collaboration;
use App\Models\CalendarEvent;
use Illuminate\Foundation\Http\FormRequest;
final class StoreCalendarEventAttachmentRequest extends FormRequest {
    public function authorize(): bool { $event=$this->route('calendarEvent'); return $event instanceof CalendarEvent && ($this->user()?->can('update',$event)??false); }
    public function rules(): array { return ['attachment'=>['required','file','max:25600','mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,csv']]; }
}
