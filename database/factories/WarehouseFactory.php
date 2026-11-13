<?php

namespace Database\Factories;

use App\Enums\WarehouseType;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('E???')),
            'name' => 'Entrepôt '.fake()->unique()->city(),
            'type' => WarehouseType::Warehouse,
            'address' => fake()->streetAddress(),
            'is_active' => true,
        ];
    }

    public function pointOfSale(): static
    {
        return $this->state(fn () => [
            'name' => 'Boutique '.fake()->unique()->city(),
            'type' => WarehouseType::Store,
        ]);
    }
}
