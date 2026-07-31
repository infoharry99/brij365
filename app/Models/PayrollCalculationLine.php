<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollCalculationLine extends Model
{
    protected $fillable = [
        'payroll_calculation_snapshot_id',
        'system_setting_id',
        'component_code',
        'component_name',
        'line_type',
        'amount_minor',
        'basis_minor',
        'rate_ppm',
        'sort_order',
        'trace',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'basis_minor' => 'integer',
            'rate_ppm' => 'integer',
            'sort_order' => 'integer',
            'trace' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new \LogicException('Payroll calculation lines are immutable.'));
        static::deleting(fn (): never => throw new \LogicException('Payroll calculation lines are immutable.'));
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(PayrollCalculationSnapshot::class, 'payroll_calculation_snapshot_id');
    }

    public function systemSetting(): BelongsTo
    {
        return $this->belongsTo(SystemSetting::class);
    }
}
