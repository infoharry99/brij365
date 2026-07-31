<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkTaskAttachment extends Model
{
    protected $touches = ['task'];

    protected $fillable = [
        'work_task_id', 'company_id', 'uploaded_by_user_id', 'disk', 'path',
        'original_filename', 'mime_type', 'size_bytes', 'checksum_sha256',
        'scan_status', 'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(WorkTask::class, 'work_task_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
