<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkTaskComment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $touches = ['workTask'];

    protected $fillable = [
        'company_id',
        'work_task_id',
        'author_user_id',
        'body',
        'mentions',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'mentions' => 'array',
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

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
