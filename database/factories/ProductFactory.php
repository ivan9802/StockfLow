<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $purchase = fake()->numberBetween(10, 4000) * 100;

        return [
            'sku' => 'TST-'.fake()->unique()->numerify('######'),
            'barcode' => fake()->unique()->numerify('2009#########'),
            'name' => ucfirst(fake()->unique()->words(3, true)),
            'description' => fake()->sentence(),
            'category_id' => Category::factory(),
            'supplier_id' => null,
            'purchase_price' => $purchase,
            'selling_price' => (int) round($purchase * 1.3, -2),
            'alert_threshold' => 5,
            'image_path' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
