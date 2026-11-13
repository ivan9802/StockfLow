<?php

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('status', PurchaseOrderStatus::values())->default(PurchaseOrderStatus::Draft->value);
            $table->date('ordered_at')->nullable();
            $table->date('expected_at')->nullable();
            $table->text('notes')->nullable();
            // Motif obligatoire quand la commande est soldée ou annulée (D-018).
            $table->string('closure_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'expected_at']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('quantity_ordered');
            $table->unsignedInteger('quantity_received')->default(0);
            $table->unsignedInteger('unit_cost');

            $table->unique(['purchase_order_id', 'product_id']);
        });

        DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT chk_poi_ordered CHECK (quantity_ordered > 0)');
        DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT chk_poi_received CHECK (quantity_received <= quantity_ordered)');

        // Un bon par livraison : plusieurs bons sur une même commande couvrent
        // les réceptions partielles. Sans commande, c'est une entrée directe (D-016).
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('delivery_note_number', 60)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('received_at')->index();
            $table->timestamps();
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_cost');

            $table->unique(['goods_receipt_id', 'product_id']);
        });

        DB::statement('ALTER TABLE goods_receipt_items ADD CONSTRAINT chk_gri_quantity CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
