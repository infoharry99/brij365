<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataImportBatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'import_number' => $this->import_number,
            'import_type' => $this->import_type,
            'source_filename' => $this->source_filename,
            'checksum' => $this->checksum,
            'status' => $this->status,
            'total_rows' => $this->total_rows,
            'valid_rows' => $this->valid_rows,
            'invalid_rows' => $this->invalid_rows,
            'preview_rows' => $this->preview_rows ?? [],
            'error_report' => $this->error_report ?? [],
            'reconciliation_summary' => $this->reconciliation_summary ?? [],
            'workflow_history' => $this->workflow_history ?? [],
            'metadata' => $this->metadata ?? [],
            'posted_at' => $this->posted_at?->toISOString(),
            'company' => $this->whenLoaded('company', fn () => [
                'code' => $this->company->code,
                'name' => $this->company->name,
            ]),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'name' => $this->createdBy->name,
                'email' => $this->createdBy->email,
            ]),
            'posted_by' => $this->whenLoaded('postedBy', fn () => $this->postedBy ? [
                'name' => $this->postedBy->name,
                'email' => $this->postedBy->email,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
