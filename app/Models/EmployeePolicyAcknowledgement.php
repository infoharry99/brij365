<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeePolicyAcknowledgement extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'employee_id',
        'acknowledged_by_user_id',
        'policy_key',
        'policy_title',
        'policy_version',
        'status',
        'acknowledgement_note',
        'policy_snapshot',
        'workflow_history',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'policy_version' => 'integer',
            'policy_snapshot' => 'array',
            'workflow_history' => 'array',
            'acknowledged_at' => 'datetime',
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

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }
}
