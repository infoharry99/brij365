<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportSchedule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'company_id',
        'report_key',
        'label',
        'frequency',
        'format',
        'filters',
        'recipients',
        'starts_on',
        'ends_on',
        'next_run_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'recipients' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'next_run_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
