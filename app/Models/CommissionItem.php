<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommissionItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'commission_run_id',
        'commission_rule_id',
        'employee_id',
        'booking_id',
        'lead_id',
        'partner_id',
        'payroll_run_item_id',
        'period_year',
        'period_month',
        'source_amount',
        'eligible_amount',
        'commission_amount',
        'status',
        'rule_snapshot',
        'metadata',
        'payroll_included_at',
    ];

    protected function casts(): array
    {
        return [
            'source_amount' => 'decimal:2',
            'eligible_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'rule_snapshot' => 'array',
            'metadata' => 'array',
            'payroll_included_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(CommissionRun::class, 'commission_run_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class, 'commission_rule_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function payrollRunItem(): BelongsTo
    {
        return $this->belongsTo(PayrollRunItem::class);
    }
}
