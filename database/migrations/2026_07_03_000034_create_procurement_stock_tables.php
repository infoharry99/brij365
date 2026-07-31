<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('store_type', 30)->default('site')->index();
            $table->string('item_code', 80);
            $table->string('description');
            $table->string('unit', 40);
            $table->decimal('on_hand_quantity', 18, 3)->default(0);
            $table->decimal('stock_value', 18, 2)->default(0);
            $table->decimal('average_rate', 18, 4)->default(0);
            $table->decimal('minimum_stock_quantity', 18, 3)->default(0);
            $table->string('status')->default('active')->index();
            $table->dateTime('last_movement_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'project_id', 'store_type', 'item_code'], 'stock_item_scope_code_unique');
            $table->index(['company_id', 'project_id', 'status']);
            $table->index(['item_code', 'status']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_item_id')->constrained('stock_items')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('movement_number', 40)->unique();
            $table->string('movement_type', 40)->index();
            $table->date('movement_date')->index();
            $table->string('store_type', 30)->default('site')->index();
            $table->string('item_code', 80)->index();
            $table->string('description');
            $table->string('unit', 40);
            $table->decimal('quantity', 18, 3);
            $table->decimal('rate', 18, 4)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->decimal('balance_after_quantity', 18, 3)->default(0);
            $table->decimal('balance_after_value', 18, 2)->default(0);
            $table->string('source_type', 80)->index();
            $table->unsignedBigInteger('source_id')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'item_code', 'movement_type'], 'stock_movement_source_item_unique');
            $table->index(['company_id', 'project_id', 'movement_type']);
            $table->index(['goods_receipt_id', 'item_code']);
        });

        $this->backfillExistingGoodsReceiptStock();
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_items');
    }

    private function backfillExistingGoodsReceiptStock(): void
    {
        if (! Schema::hasTable('goods_receipts')) {
            return;
        }

        $movementSequence = 1001;

        DB::table('goods_receipts')
            ->where('status', 'received')
            ->orderBy('id')
            ->get()
            ->each(function (object $receipt) use (&$movementSequence): void {
                $items = json_decode((string) $receipt->items, true);

                if (! is_array($items)) {
                    return;
                }

                foreach ($items as $item) {
                    if (! is_array($item) || empty($item['item_code'])) {
                        continue;
                    }

                    $itemCode = strtoupper((string) $item['item_code']);
                    $quantity = round((float) ($item['accepted_quantity'] ?? 0), 3);

                    if ($quantity <= 0) {
                        continue;
                    }

                    $rate = round((float) ($item['rate'] ?? 0), 4);
                    $amount = round($quantity * $rate, 2);
                    $now = now();

                    $stockItem = DB::table('stock_items')
                        ->where('company_id', $receipt->company_id)
                        ->where('project_id', $receipt->project_id)
                        ->where('store_type', 'site')
                        ->where('item_code', $itemCode)
                        ->first();

                    if (! $stockItem) {
                        $stockItemId = DB::table('stock_items')->insertGetId([
                            'company_id' => $receipt->company_id,
                            'project_id' => $receipt->project_id,
                            'store_type' => 'site',
                            'item_code' => $itemCode,
                            'description' => (string) ($item['description'] ?? $itemCode),
                            'unit' => (string) ($item['unit'] ?? 'unit'),
                            'on_hand_quantity' => $quantity,
                            'stock_value' => $amount,
                            'average_rate' => $quantity > 0 ? round($amount / $quantity, 4) : 0,
                            'minimum_stock_quantity' => 0,
                            'status' => 'active',
                            'last_movement_at' => $now,
                            'metadata' => json_encode(['source' => 'migration_backfill']),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                        $balanceQuantity = $quantity;
                        $balanceValue = $amount;
                    } else {
                        $stockItemId = $stockItem->id;
                        $balanceQuantity = round((float) $stockItem->on_hand_quantity + $quantity, 3);
                        $balanceValue = round((float) $stockItem->stock_value + $amount, 2);

                        DB::table('stock_items')
                            ->where('id', $stockItemId)
                            ->update([
                                'description' => (string) ($item['description'] ?? $stockItem->description),
                                'unit' => (string) ($item['unit'] ?? $stockItem->unit),
                                'on_hand_quantity' => $balanceQuantity,
                                'stock_value' => $balanceValue,
                                'average_rate' => $balanceQuantity > 0 ? round($balanceValue / $balanceQuantity, 4) : 0,
                                'last_movement_at' => $now,
                                'updated_at' => $now,
                            ]);
                    }

                    DB::table('stock_movements')->insert([
                        'company_id' => $receipt->company_id,
                        'project_id' => $receipt->project_id,
                        'stock_item_id' => $stockItemId,
                        'purchase_order_id' => $receipt->purchase_order_id,
                        'goods_receipt_id' => $receipt->id,
                        'created_by_user_id' => $receipt->received_by_user_id,
                        'movement_number' => sprintf('STM-%04d', $movementSequence++),
                        'movement_type' => 'inward',
                        'movement_date' => $receipt->received_on,
                        'store_type' => 'site',
                        'item_code' => $itemCode,
                        'description' => (string) ($item['description'] ?? $itemCode),
                        'unit' => (string) ($item['unit'] ?? 'unit'),
                        'quantity' => $quantity,
                        'rate' => $rate,
                        'amount' => $amount,
                        'balance_after_quantity' => $balanceQuantity,
                        'balance_after_value' => $balanceValue,
                        'source_type' => 'goods_receipt',
                        'source_id' => $receipt->id,
                        'metadata' => json_encode([
                            'source' => 'migration_backfill',
                            'grn_number' => $receipt->grn_number,
                            'delivery_challan_number' => $receipt->delivery_challan_number,
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }
};
