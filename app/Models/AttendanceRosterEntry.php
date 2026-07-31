<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRosterEntry extends Model
{
    protected $fillable = ['attendance_roster_id', 'company_id', 'employee_id', 'attendance_shift_id', 'attendance_rotation_rule_id', 'work_date', 'entry_type', 'source', 'occurrence_key', 'starts_at', 'ends_at', 'metadata', 'lock_version'];

    protected function casts(): array
    {
        return ['work_date' => 'date', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'metadata' => 'array', 'lock_version' => 'integer'];
    }

    public function roster(): BelongsTo { return $this->belongsTo(AttendanceRoster::class, 'attendance_roster_id'); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function shift(): BelongsTo { return $this->belongsTo(AttendanceShift::class, 'attendance_shift_id'); }
    public function rotationRule(): BelongsTo { return $this->belongsTo(AttendanceRotationRule::class, 'attendance_rotation_rule_id'); }
}
