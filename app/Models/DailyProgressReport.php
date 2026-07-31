<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DailyProgressReport extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'prepared_by_user_id',
        'approved_by_user_id',
        'report_number',
        'report_date',
        'weather',
        'manpower_count',
        'manpower_breakup',
        'progress_items',
        'materials_used',
        'equipment_used',
        'work_summary',
        'safety_observations',
        'quality_observations',
        'blockers',
        'status',
        'workflow_history',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'manpower_count' => 'integer',
            'manpower_breakup' => 'array',
            'progress_items' => 'array',
            'materials_used' => 'array',
            'equipment_used' => 'array',
            'workflow_history' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
