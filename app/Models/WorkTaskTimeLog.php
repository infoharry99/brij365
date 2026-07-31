<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkTaskTimeLog extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $touches = ['workTask'];

    protected $fillable = [
        'company_id',
        'work_task_id',
        'user_id',
        'logged_on',
        'minutes',
        'note',
        'source',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'logged_on' => 'date',
            'minutes' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function workTask(): BelongsTo
    {
        return $this->belongsTo(WorkTask::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
