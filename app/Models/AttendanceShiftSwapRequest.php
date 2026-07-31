<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceShiftSwapRequest extends Model
{
    protected $fillable = ['company_id', 'requester_employee_id', 'source_roster_entry_id', 'target_roster_entry_id', 'requested_by_user_id', 'decided_by_user_id', 'request_number', 'status', 'reason', 'decision_note', 'decided_at', 'lock_version'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'lock_version' => 'integer'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function requesterEmployee(): BelongsTo { return $this->belongsTo(Employee::class, 'requester_employee_id'); }
    public function sourceEntry(): BelongsTo { return $this->belongsTo(AttendanceRosterEntry::class, 'source_roster_entry_id'); }
    public function targetEntry(): BelongsTo { return $this->belongsTo(AttendanceRosterEntry::class, 'target_roster_entry_id'); }
    public function requestedBy(): BelongsTo { return $this->belongsTo(User::class, 'requested_by_user_id'); }
    public function decidedBy(): BelongsTo { return $this->belongsTo(User::class, 'decided_by_user_id'); }
}
