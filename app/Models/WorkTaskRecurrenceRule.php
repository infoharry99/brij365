<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkTaskRecurrenceRule extends Model
{
    protected $fillable = ['company_id', 'root_work_task_id', 'frequency', 'interval', 'timezone', 'next_run_at', 'until_at', 'last_generated_at', 'status', 'generation_count', 'lock_version', 'metadata'];
    protected function casts(): array { return ['next_run_at' => 'datetime', 'until_at' => 'datetime', 'last_generated_at' => 'datetime', 'metadata' => 'array']; }
    public function rootTask(): BelongsTo { return $this->belongsTo(WorkTask::class, 'root_work_task_id'); }
    public function occurrences(): HasMany { return $this->hasMany(WorkTaskRecurrenceOccurrence::class); }
}
