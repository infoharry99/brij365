<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AttendanceSourceEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'employee_id',
        'recorded_by_user_id',
        'work_date',
        'occurred_at',
        'timezone',
        'event_type',
        'source',
        'source_reference',
        'event_key',
        'payload_hash',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Attendance source events are immutable and cannot be updated.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Attendance source events are append-only and cannot be deleted.');
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
