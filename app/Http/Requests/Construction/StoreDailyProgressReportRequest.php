<?php

namespace App\Http\Requests\Construction;

use App\Models\ConstructionMilestone;
use App\Models\DailyProgressReport;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use App\Support\OperationalInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDailyProgressReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', DailyProgressReport::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'report_date' => ['required', 'date', 'before_or_equal:today'],
            'weather' => ['nullable', 'string', 'max:120'],
            'manpower_count' => ['required', 'integer', 'min:0', 'max:50000'],
            'manpower_breakup' => ['nullable', 'array', 'max:50'],
            'manpower_breakup.*.category' => ['required_with:manpower_breakup', 'string', 'max:120'],
            'manpower_breakup.*.count' => ['required_with:manpower_breakup', 'integer', 'min:0', 'max:50000'],
            'progress_items' => ['required', 'array', 'min:1', 'max:100'],
            'progress_items.*.milestone_id' => ['required', 'integer', Rule::exists('construction_milestones', 'id')],
            'progress_items.*.work_done' => ['required', 'string', 'max:1000'],
            'progress_items.*.progress_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'materials_used' => ['nullable', 'array', 'max:100'],
            'materials_used.*.item_code' => ['required_with:materials_used', 'string', 'max:80'],
            'materials_used.*.description' => ['required_with:materials_used', 'string', 'max:255'],
            'materials_used.*.unit' => ['required_with:materials_used', 'string', 'max:40'],
            'materials_used.*.quantity' => ['required_with:materials_used', 'numeric', 'min:0.01', app(OperationalInputPolicy::class)->procurementQuantityMaxRule()],
            'equipment_used' => ['nullable', 'array', 'max:100'],
            'equipment_used.*.name' => ['required_with:equipment_used', 'string', 'max:120'],
            'equipment_used.*.hours' => ['required_with:equipment_used', 'numeric', 'min:0.01', app(OperationalInputPolicy::class)->equipmentHoursMaxRule()],
            'work_summary' => ['required', 'string', 'max:8000'],
            'safety_observations' => ['nullable', 'string', 'max:5000'],
            'quality_observations' => ['nullable', 'string', 'max:5000'],
            'blockers' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();
                $project = Project::query()->whereKey($this->integer('project_id'))->first();

                if (
                    ! $project
                    || ! $user
                    || ! app(CompanyScopeService::class)->allows($user, $project->company_id)
                    || $project->status !== 'active'
                ) {
                    $validator->errors()->add('project_id', 'The selected project is not active for your company.');

                    return;
                }

                if (DailyProgressReport::query()
                    ->where('project_id', $project->id)
                    ->whereDate('report_date', $this->date('report_date')?->toDateString())
                    ->exists()) {
                    $validator->errors()->add('report_date', 'A daily progress report already exists for this project and date.');
                }

                $milestoneIds = collect($this->input('progress_items', []))
                    ->pluck('milestone_id')
                    ->map(fn ($id): int => (int) $id)
                    ->unique()
                    ->values();

                $validMilestoneCount = ConstructionMilestone::query()
                    ->where('company_id', $project->company_id)
                    ->where('project_id', $project->id)
                    ->whereIn('id', $milestoneIds)
                    ->count();

                if ($milestoneIds->isNotEmpty() && $validMilestoneCount !== $milestoneIds->count()) {
                    $validator->errors()->add('progress_items', 'All progress milestones must belong to the selected project and company.');
                }

                $manpowerTotal = collect($this->input('manpower_breakup', []))->sum(fn (array $row): int => (int) ($row['count'] ?? 0));
                if ($manpowerTotal > 0 && $manpowerTotal !== $this->integer('manpower_count')) {
                    $validator->errors()->add('manpower_count', 'Manpower count must match the manpower breakup total.');
                }
            },
        ];
    }
}