<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkTaskReminderDelivery extends Model
{
    protected $fillable = ['work_task_id', 'recipient_user_id', 'reminder_at', 'minutes_before', 'status', 'attempts', 'idempotency_key', 'sent_at', 'failed_at', 'error_code'];
    protected function casts(): array { return ['reminder_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime']; }
    public function task(): BelongsTo { return $this->belongsTo(WorkTask::class, 'work_task_id'); }
    public function recipient(): BelongsTo { return $this->belongsTo(User::class, 'recipient_user_id'); }
}
