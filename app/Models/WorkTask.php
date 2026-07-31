<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkTask extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'created_by_user_id',
        'assigned_to_user_id',
        'task_number',
        'client_token',
        'lock_version',
        'title',
        'description',
        'priority',
        'status',
        'due_at',
        'started_at',
        'completed_at',
        'module_context',
        'related_type',
        'related_id',
        'checklist',
        'workflow_history',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'checklist' => 'array',
            'workflow_history' => 'array',
            'metadata' => 'array',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (WorkTask $task): void {
            if (! $task->isDirty('lock_version')) {
                $task->lock_version = max(1, (int) $task->getOriginal('lock_version')) + 1;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assignees(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'work_task_assignees', 'work_task_id', 'user_id')->withTimestamps();
    }

    public function scopeForAssignee($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('assigned_to_user_id', $userId)
              ->orWhereHas('assignees', function ($aq) use ($userId) {
                  $aq->where('users.id', $userId);
              });
        });
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(WorkTaskComment::class)->oldest();
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(WorkTaskSubtask::class)->oldest();
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(WorkTaskTimeLog::class)->oldest();
    }

    public function transferRequests(): HasMany
    {
        return $this->hasMany(WorkTaskTransferRequest::class)->latest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(WorkTaskAttachment::class)->oldest();
    }

    public function completionApprovals(): HasMany
    {
        return $this->hasMany(WorkTaskCompletionApproval::class)->latest();
    }

    public function recurrenceRule(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(WorkTaskRecurrenceRule::class, 'root_work_task_id');
    }

    public function reminderDeliveries(): HasMany
    {
        return $this->hasMany(WorkTaskReminderDelivery::class);
    }
}
