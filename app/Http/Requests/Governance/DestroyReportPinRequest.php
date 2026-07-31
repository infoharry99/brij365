<?php
namespace App\Http\Requests\Governance;
use App\Models\ReportPin;
use Illuminate\Foundation\Http\FormRequest;
class DestroyReportPinRequest extends FormRequest { public function authorize(): bool { $pin=$this->route('reportPin'); return $pin instanceof ReportPin && $this->user()?->can('reports.view')===true && $pin->user_id===$this->user()?->id; } public function rules(): array { return []; } }
