<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceShiftSegment extends Model
{
    protected $fillable = [
        'attendance_shift_id',
        'sequence',
        'label',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return ['sequence' => 'integer'];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(AttendanceShift::class, 'attendance_shift_id');
    }
}
