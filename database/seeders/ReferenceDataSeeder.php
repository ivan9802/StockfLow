<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\WarehouseType;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Données sans lesquelles l'application ne démarre pas : les deux emplacements
 * (D-020) et un premier administrateur. Sans danger à relancer.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        Warehouse::firstOrCreate(['code' => 'ENT'], [
            'name' => 'Entrepôt central', 'type' => WarehouseType::Warehouse, 'address' => 'Zone industrielle de Yopougon, Abidjan',
        ]);
        Warehouse::firstOrCreate(['code' => 'PDV'], [
            'name' => 'Point de vente Plateau', 'type' => WarehouseType::Store, 'address' => 'Avenue Chardy, Le Plateau, Abidjan',
        ]);

        if (User::where('role', Role::Admin->value)->exists()) {
            return;
        }

        // Le mot de passe initial vient de ADMIN_PASSWORD, sinon il est tiré au hasard
        // et affiché une seule fois. Dans les deux cas il doit être changé à la première connexion.
        $password = env('ADMIN_PASSWORD') ?: Str::password(16, symbols: false);
        $email = env('ADMIN_EMAIL', 'admin@stockflow.example');

        User::create([
            'name' => env('ADMIN_NAME', 'Administrateur'),
            'email' => $email,
            'password' => $password,
            'role' => Role::Admin,
            'must_change_password' => true,
            'is_active' => true,
        ]);

        $this->command?->warn("Compte administrateur créé : $email / $password (à changer à la première connexion).");
    }
}
