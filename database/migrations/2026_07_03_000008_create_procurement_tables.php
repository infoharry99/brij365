<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('vendor_code', 40);
            $table->string('name');
            $table->string('vendor_type')->default('material')->index();
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 30)->nullable()->index();
            $table->string('gstin', 20)->nullable()->index();
            $table->string('pan', 20)->nullable()->index();
            $table->json('address')->nullable();
            $table->json('bank_details')->nullable();
            $table->json('compliance_documents')->nullable();
            $table->string('status')->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'vendor_code']);
            $table->unique(['company_id', 'gstin'], 'vendor_company_gstin_unique');
        });

        Schema::create('purchase_requisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requisition_number', 40)->unique();
            $table->string('department')->index();
            $table->date('required_by')->index();
            $table->string('priority')->default('normal')->index();
            $table->string('status')->default('submitted')->index();
            $table->json('items');
            $table->decimal('estimated_total', 16, 2)->default(0);
            $table->text('purpose')->nullable();
            $table->json('workflow_history')->nullable();
            $table->dateTime('approved_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_requisition_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('po_number', 40)->unique();
            $table->date('po_date')->index();
            $table->date('expected_delivery_on')->nullable()->index();
            $table->string('status')->default('draft')->index();
            $table->string('payment_terms')->nullable();
            $table->json('items');
            $table->decimal('subtotal', 16, 2)->default(0);
            $table->decimal('tax_amount', 16, 2)->default(0);
            $table->decimal('total_amount', 16, 2)->default(0);
            $table->text('terms')->nullable();
            $table->json('workflow_history')->nullable();
            $table->dateTime('approved_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('grn_number', 40)->unique();
            $table->date('received_on')->index();
            $table->string('delivery_challan_number')->nullable()->index();
            $table->string('status')->default('received')->index();
            $table->json('items');
            $table->decimal('accepted_total', 16, 2)->default(0);
            $table->text('quality_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_requisitions');
        Schema::dropIfExists('vendors');
    }
};
