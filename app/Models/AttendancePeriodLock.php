<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendancePeriodLock extends Model
{
    protected $fillable = ['company_id', 'period_start', 'period_end', 'version', 'status', 'finalized_by_user_id', 'reopened_by_user_id', 'finalized_at', 'reopened_at', 'reopen_reason', 'source_hash', 'rule_context', 'lock_version'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'version' => 'integer', 'finalized_at' => 'datetime', 'reopened_at' => 'datetime', 'rule_context' => 'array', 'lock_version' => 'integer'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function snapshots(): HasMany { return $this->hasMany(PayrollAttendanceSnapshot::class); }
    public function finalizedBy(): BelongsTo { return $this->belongsTo(User::class, 'finalized_by_user_id'); }
}
