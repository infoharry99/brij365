<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollBankTransferBatch extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'payroll_run_id',
        'prepared_by_user_id',
        'released_by_user_id',
        'batch_number',
        'bank_name',
        'payment_date',
        'status',
        'item_count',
        'control_total',
        'checksum',
        'csv_payload',
        'validation_summary',
        'workflow_history',
        'prepared_at',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'control_total' => 'decimal:2',
            'validation_summary' => 'array',
            'workflow_history' => 'array',
            'prepared_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollBankTransferItem::class);
    }
}
