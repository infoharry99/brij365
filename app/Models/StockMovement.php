<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'project_id',
        'stock_item_id',
        'purchase_order_id',
        'goods_receipt_id',
        'created_by_user_id',
        'movement_number',
        'movement_type',
        'movement_date',
        'store_type',
        'item_code',
        'description',
        'unit',
        'quantity',
        'rate',
        'amount',
        'balance_after_quantity',
        'balance_after_value',
        'source_type',
        'source_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
            'quantity' => 'decimal:3',
            'rate' => 'decimal:4',
            'amount' => 'decimal:2',
            'balance_after_quantity' => 'decimal:3',
            'balance_after_value' => 'decimal:2',
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

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
