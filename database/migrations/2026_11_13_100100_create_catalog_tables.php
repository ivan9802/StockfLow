<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            // Préfixe des SKU de la catégorie, par exemple IMP pour les imprimantes.
            $table->string('code', 4)->unique();
            $table->string('name', 100)->unique();
            $table->string('description', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->index();
            $table->string('contact_name', 120)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->unsignedSmallInteger('lead_time_days')->default(7);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 30)->unique();
            $table->string('barcode', 13)->unique();
            $table->string('name', 180)->index();
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            // Montants en F CFA entiers : le XOF n'a pas de centimes (D-012).
            $table->unsignedInteger('purchase_price');
            $table->unsignedInteger('selling_price');
            // Seuil comparé au stock total, tous emplacements confondus (D-008).
            $table->unsignedInteger('alert_threshold')->default(0);
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('categories');
    }
};
