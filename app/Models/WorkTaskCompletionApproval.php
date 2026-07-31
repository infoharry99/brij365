<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkTaskCompletionApproval extends Model
{
    protected $touches = ['task'];

    protected $fillable = ['company_id', 'work_task_id', 'requested_by_user_id', 'decided_by_user_id', 'status', 'request_note', 'decision_note', 'decided_at'];

    protected function casts(): array { return ['decided_at' => 'datetime']; }

    public function task(): BelongsTo { return $this->belongsTo(WorkTask::class, 'work_task_id'); }
    public function requestedBy(): BelongsTo { return $this->belongsTo(User::class, 'requested_by_user_id'); }
    public function decidedBy(): BelongsTo { return $this->belongsTo(User::class, 'decided_by_user_id'); }
}
