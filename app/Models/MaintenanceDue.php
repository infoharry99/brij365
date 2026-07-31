<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceDue extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'booking_id',
        'customer_id',
        'project_unit_id',
        'raised_by_user_id',
        'paid_by_user_id',
        'due_number',
        'period_start_on',
        'period_end_on',
        'due_on',
        'amount',
        'paid_amount',
        'balance_amount',
        'status',
        'paid_at',
        'payment_reference',
        'last_reminded_at',
        'workflow_history',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'period_start_on' => 'date',
            'period_end_on' => 'date',
            'due_on' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'last_reminded_at' => 'datetime',
            'workflow_history' => 'array',
            'metadata' => 'array',
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

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProjectUnit::class, 'project_unit_id');
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by_user_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }
}
