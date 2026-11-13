<?php

use App\Enums\WarehouseType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->enum('type', WarehouseType::values());
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Emplacement d'affectation : obligatoire pour un vendeur, il fixe d'où il vend.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('role')
                ->constrained('warehouses')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
        Schema::dropIfExists('warehouses');
    }
};
