<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollBankTransferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_bank_transfer_batch_id',
        'payroll_run_item_id',
        'employee_id',
        'employee_code',
        'beneficiary_name',
        'bank_account_number_encrypted',
        'bank_account_last4',
        'ifsc_code',
        'amount',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'bank_account_number_encrypted' => 'encrypted',
            'amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PayrollBankTransferBatch::class, 'payroll_bank_transfer_batch_id');
    }

    public function payrollRunItem(): BelongsTo
    {
        return $this->belongsTo(PayrollRunItem::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
