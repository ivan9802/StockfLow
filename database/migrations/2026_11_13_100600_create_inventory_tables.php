<?php

use App\Enums\InventoryStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            // Inventaire tournant limité à une catégorie ; nul pour un inventaire complet.
            $table->foreignId('category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->enum('status', InventoryStatus::values())->default(InventoryStatus::InProgress->value);
            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('validated_at')->nullable();
            $table->text('notes')->nullable();
            // Commentaire de l'administrateur quand il renvoie la session en comptage.
            $table->string('review_comment', 500)->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_session_id')->constrained('inventory_sessions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            // Stock lu au moment où la ligne est comptée, pas à l'ouverture (D-007).
            $table->unsignedInteger('theoretical_quantity')->nullable();
            $table->unsignedInteger('counted_quantity')->nullable();
            $table->integer('variance')->nullable();
            $table->dateTime('counted_at')->nullable();
            $table->foreignId('counted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('justification', 500)->nullable();

            $table->unique(['inventory_session_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_sessions');
    }
};
