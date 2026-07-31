<?php

namespace App\Http\Requests\Construction;

use App\Models\DailyProgressReport;
use Illuminate\Foundation\Http\FormRequest;

class RejectDailyProgressReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $report = $this->route('dailyProgressReport');

        return $report instanceof DailyProgressReport
            && $this->user()?->can('approve', $report) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
