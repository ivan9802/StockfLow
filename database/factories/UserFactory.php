<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('mot-de-passe-test'),
            'must_change_password' => false,
            'role' => Role::StockManager,
            'warehouse_id' => null,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => Role::Admin]);
    }

    public function stockManager(): static
    {
        return $this->state(fn () => ['role' => Role::StockManager]);
    }

    /** Vendeur affecté à un point de vente (créé au besoin). */
    public function salesAgent(?Warehouse $store = null): static
    {
        return $this->state(fn () => [
            'role' => Role::SalesAgent,
            'warehouse_id' => $store?->id ?? Warehouse::factory()->pointOfSale(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
