<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectUnit extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'unit_code',
        'tower',
        'floor',
        'unit_number',
        'unit_type',
        'carpet_area_sqft',
        'saleable_area_sqft',
        'base_rate',
        'base_price',
        'floor_rise',
        'parking_charges',
        'other_charges',
        'tax_amount',
        'total_price',
        'status',
        'reserved_until',
    ];

    protected function casts(): array
    {
        return [
            'carpet_area_sqft' => 'decimal:2',
            'saleable_area_sqft' => 'decimal:2',
            'base_rate' => 'decimal:2',
            'base_price' => 'decimal:2',
            'floor_rise' => 'decimal:2',
            'parking_charges' => 'decimal:2',
            'other_charges' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_price' => 'decimal:2',
            'reserved_until' => 'datetime',
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

    public function activeBooking(): HasOne
    {
        return $this->hasOne(Booking::class)->whereIn('status', ['draft', 'confirmed', 'agreement_pending', 'registered']);
    }

    public function priceVersions()
    {
        return $this->hasMany(UnitPriceVersion::class);
    }

    public function isBookable(): bool
    {
        return $this->status === 'available'
            || ($this->status === 'reserved' && $this->reserved_until !== null && $this->reserved_until->isPast());
    }
}
