<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractorMeasurement extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'vendor_id',
        'submitted_by_user_id',
        'approved_by_user_id',
        'measurement_number',
        'measurement_date',
        'bill_reference',
        'status',
        'measured_total',
        'certified_total',
        'lines',
        'remarks',
        'workflow_history',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'measurement_date' => 'date',
            'measured_total' => 'decimal:2',
            'certified_total' => 'decimal:2',
            'lines' => 'array',
            'workflow_history' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
