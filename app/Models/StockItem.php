<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'store_type',
        'item_code',
        'description',
        'unit',
        'on_hand_quantity',
        'stock_value',
        'average_rate',
        'minimum_stock_quantity',
        'status',
        'last_movement_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'on_hand_quantity' => 'decimal:3',
            'stock_value' => 'decimal:2',
            'average_rate' => 'decimal:4',
            'minimum_stock_quantity' => 'decimal:3',
            'last_movement_at' => 'datetime',
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

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isBelowMinimum(): bool
    {
        return (float) $this->minimum_stock_quantity > 0
            && (float) $this->on_hand_quantity <= (float) $this->minimum_stock_quantity;
    }
}
