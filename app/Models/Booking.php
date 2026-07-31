<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'project_unit_id',
        'unit_price_version_id',
        'customer_id',
        'lead_id',
        'partner_id',
        'booked_by_user_id',
        'booking_code',
        'status',
        'booked_on',
        'agreement_value',
        'discount_amount',
        'tax_amount',
        'net_receivable',
        'booking_amount',
        'commercials',
    ];

    protected function casts(): array
    {
        return [
            'booked_on' => 'date',
            'agreement_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'net_receivable' => 'decimal:2',
            'booking_amount' => 'decimal:2',
            'commercials' => 'array',
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

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProjectUnit::class, 'project_unit_id');
    }

    public function unitPriceVersion(): BelongsTo
    {
        return $this->belongsTo(UnitPriceVersion::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by_user_id');
    }

    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(BookingPaymentSchedule::class)->orderBy('sequence');
    }

    public function collectionReceipts(): HasMany
    {
        return $this->hasMany(CollectionReceipt::class)->latest('receipt_date');
    }

    public function paymentRequests(): HasMany
    {
        return $this->hasMany(PaymentRequest::class)->latest();
    }

    public function commissionItems(): HasMany
    {
        return $this->hasMany(CommissionItem::class);
    }

    public function managedDocuments(): HasMany
    {
        return $this->hasMany(ManagedDocument::class, 'owner_id')->where('owner_type', 'booking');
    }

    public function serviceTickets(): HasMany
    {
        return $this->hasMany(ServiceTicket::class);
    }
}
