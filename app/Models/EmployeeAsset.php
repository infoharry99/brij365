<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeAsset extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'employee_id',
        'assigned_by_user_id',
        'recovered_by_user_id',
        'asset_code',
        'category',
        'name',
        'serial_number',
        'status',
        'condition',
        'assigned_on',
        'recovered_on',
        'estimated_value',
        'metadata',
        'workflow_history',
    ];

    protected function casts(): array
    {
        return [
            'assigned_on' => 'date',
            'recovered_on' => 'date',
            'estimated_value' => 'decimal:2',
            'metadata' => 'array',
            'workflow_history' => 'array',
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

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function recoveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recovered_by_user_id');
    }
}
