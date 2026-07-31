<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRoster extends Model
{
    public const STATUSES = ['draft', 'published', 'locked', 'cancelled'];

    protected $fillable = ['company_id', 'name', 'period_start', 'period_end', 'timezone', 'status', 'created_by_user_id', 'published_by_user_id', 'locked_by_user_id', 'cancelled_by_user_id', 'published_at', 'locked_at', 'cancelled_at', 'status_note', 'rule_context', 'lock_version'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'published_at' => 'datetime', 'locked_at' => 'datetime', 'cancelled_at' => 'datetime', 'rule_context' => 'array', 'lock_version' => 'integer'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function entries(): HasMany { return $this->hasMany(AttendanceRosterEntry::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
}
