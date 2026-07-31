<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkTaskRecurrenceOccurrence extends Model
{
    protected $fillable = ['work_task_recurrence_rule_id', 'source_work_task_id', 'generated_work_task_id', 'scheduled_for', 'status', 'idempotency_key', 'metadata'];
    protected function casts(): array { return ['scheduled_for' => 'datetime', 'metadata' => 'array']; }
    public function rule(): BelongsTo { return $this->belongsTo(WorkTaskRecurrenceRule::class, 'work_task_recurrence_rule_id'); }
    public function generatedTask(): BelongsTo { return $this->belongsTo(WorkTask::class, 'generated_work_task_id'); }
}
