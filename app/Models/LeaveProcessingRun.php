<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveProcessingRun extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'created_by_user_id',
        'posted_by_user_id',
        'run_number',
        'period_year',
        'processing_type',
        'status',
        'is_dry_run',
        'rules_snapshot',
        'summary',
        'line_items',
        'workflow_history',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'is_dry_run' => 'boolean',
            'rules_snapshot' => 'array',
            'summary' => 'array',
            'line_items' => 'array',
            'workflow_history' => 'array',
            'posted_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }
}
