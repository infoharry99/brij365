<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceRegularizationRequest extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'employee_id',
        'attendance_record_id',
        'requested_by_user_id',
        'decided_by_user_id',
        'request_number',
        'status',
        'work_date',
        'requested_check_in_at',
        'requested_check_out_at',
        'reason',
        'decision_note',
        'workflow_history',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'requested_check_in_at' => 'datetime',
            'requested_check_out_at' => 'datetime',
            'workflow_history' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
