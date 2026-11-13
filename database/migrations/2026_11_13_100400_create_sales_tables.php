<?php

use App\Enums\SaleStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('customer_name', 150)->nullable();
            $table->enum('status', SaleStatus::values())->default(SaleStatus::Completed->value);
            $table->unsignedBigInteger('total_amount');
            $table->dateTime('sold_at')->index();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'sold_at']);
            $table->index(['warehouse_id', 'sold_at']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            // Prix du jour de la vente : il survit à un changement de tarif.
            $table->unsignedInteger('unit_price');
            $table->unsignedBigInteger('line_total');

            $table->unique(['sale_id', 'product_id']);
        });

        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT chk_sale_items_quantity CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
