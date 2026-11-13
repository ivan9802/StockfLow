<?php

use App\Enums\MovementDirection;
use App\Enums\MovementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            // Seuil propre à l'emplacement, pour le réassort de la boutique (D-008).
            $table->unsignedInteger('reorder_point')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id']);
            $table->index(['warehouse_id', 'quantity']);
        });

        // Dernier filet contre le stock négatif : si le service laissait passer
        // une sortie de trop, MySQL rejetterait l'écriture.
        DB::statement('ALTER TABLE stock_levels ADD CONSTRAINT chk_stock_levels_quantity CHECK (quantity >= 0)');

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('type', MovementType::values());
            $table->enum('direction', MovementDirection::values());
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('stock_before');
            $table->unsignedInteger('stock_after');
            $table->string('reason', 500)->nullable();
            $table->string('reference', 30)->index();
            // Document d'origine : sale, goods_receipt, stock_transfer, inventory_session.
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            // Pas de updated_at : un mouvement ne change jamais.
            $table->timestamp('created_at')->index();

            $table->index(['product_id', 'warehouse_id', 'created_at'], 'stock_movements_card_index');
            $table->index(['type', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });

        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT chk_stock_movements_quantity CHECK (quantity > 0)');

        // Compteurs des références de documents et des SKU (D-017).
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 10);
            // 0 pour les compteurs sans remise à zéro annuelle (SKU, codes-barres).
            $table->unsignedSmallInteger('year')->default(0);
            $table->unsignedInteger('last_number')->default(0);

            $table->unique(['prefix', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_levels');
    }
};
