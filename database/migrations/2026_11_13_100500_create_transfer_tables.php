<?php

use App\Enums\TransferStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Transfert en une étape : sortie et entrée dans la même transaction (D-006).
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('status', TransferStatus::values())->default(TransferStatus::Completed->value);
            $table->string('notes', 500)->nullable();
            $table->dateTime('transferred_at')->index();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE stock_transfers ADD CONSTRAINT chk_transfer_warehouses CHECK (from_warehouse_id <> to_warehouse_id)');

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('quantity');

            $table->unique(['stock_transfer_id', 'product_id']);
        });

        DB::statement('ALTER TABLE stock_transfer_items ADD CONSTRAINT chk_transfer_items_quantity CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
    }
};
