<?php
namespace App\Http\Requests\Governance;
use App\Models\ReportSchedule;
use Illuminate\Foundation\Http\FormRequest;
class ArchiveReportScheduleRequest extends FormRequest { public function authorize(): bool { $schedule=$this->route('reportSchedule'); return $schedule instanceof ReportSchedule && $this->user()?->can('reports.view')===true && $schedule->user_id===$this->user()?->id; } public function rules(): array { return []; } }
