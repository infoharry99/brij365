<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyProgressReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'report_number' => $this->report_number,
            'report_date' => $this->report_date?->toDateString(),
            'weather' => $this->weather,
            'manpower_count' => $this->manpower_count,
            'manpower_breakup' => $this->manpower_breakup ?? [],
            'progress_items' => $this->progress_items ?? [],
            'materials_used' => $this->materials_used ?? [],
            'equipment_used' => $this->equipment_used ?? [],
            'work_summary' => $this->work_summary,
            'safety_observations' => $this->safety_observations,
            'quality_observations' => $this->quality_observations,
            'blockers' => $this->blockers,
            'status' => $this->status,
            'workflow_history' => $this->workflow_history ?? [],
            'approved_at' => $this->approved_at?->toISOString(),
            'project' => $this->whenLoaded('project', fn (): array => [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ]),
            'prepared_by' => $this->whenLoaded('preparedBy', fn (): ?array => $this->preparedBy ? [
                'id' => $this->preparedBy->id,
                'name' => $this->preparedBy->name,
                'email' => $this->preparedBy->email,
            ] : null),
            'approved_by' => $this->whenLoaded('approvedBy', fn (): ?array => $this->approvedBy ? [
                'id' => $this->approvedBy->id,
                'name' => $this->approvedBy->name,
                'email' => $this->approvedBy->email,
            ] : null),
        ];
    }
}
