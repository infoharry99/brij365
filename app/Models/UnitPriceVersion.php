<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitPriceVersion extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'project_unit_id',
        'created_by_user_id',
        'approved_by_user_id',
        'price_code',
        'version_number',
        'status',
        'effective_from',
        'effective_to',
        'base_rate',
        'base_price',
        'floor_premium',
        'location_premium',
        'parking_charges',
        'other_charges',
        'tax_rate_percent',
        'gross_price_before_tax',
        'tax_amount',
        'total_price',
        'charge_breakup',
        'workflow_history',
        'metadata',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'base_rate' => 'decimal:2',
            'base_price' => 'decimal:2',
            'floor_premium' => 'decimal:2',
            'location_premium' => 'decimal:2',
            'parking_charges' => 'decimal:2',
            'other_charges' => 'decimal:2',
            'tax_rate_percent' => 'decimal:4',
            'gross_price_before_tax' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_price' => 'decimal:2',
            'charge_breakup' => 'array',
            'workflow_history' => 'array',
            'metadata' => 'array',
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

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProjectUnit::class, 'project_unit_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
