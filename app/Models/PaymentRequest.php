<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentRequest extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'booking_id',
        'booking_payment_schedule_id',
        'customer_id',
        'created_by_user_id',
        'paid_by_user_id',
        'collection_receipt_id',
        'request_number',
        'gateway_provider',
        'gateway_reference',
        'status',
        'amount',
        'currency',
        'purpose',
        'expires_at',
        'paid_at',
        'payment_mode',
        'instrument_number',
        'checksum',
        'gateway_payload',
        'workflow_history',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'gateway_payload' => 'array',
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

    public function paymentSchedule(): BelongsTo
    {
        return $this->belongsTo(BookingPaymentSchedule::class, 'booking_payment_schedule_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    public function collectionReceipt(): BelongsTo
    {
        return $this->belongsTo(CollectionReceipt::class);
    }
}
