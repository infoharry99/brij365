<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRotationRule extends Model
{
    protected $fillable = ['company_id', 'employee_id', 'name', 'anchor_date', 'cycle_days', 'pattern', 'generation_horizon_days', 'rule_context', 'status', 'created_by_user_id', 'lock_version'];

    protected function casts(): array
    {
        return ['anchor_date' => 'date', 'pattern' => 'array', 'cycle_days' => 'integer', 'generation_horizon_days' => 'integer', 'rule_context' => 'array', 'lock_version' => 'integer'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function entries(): HasMany { return $this->hasMany(AttendanceRosterEntry::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
}
