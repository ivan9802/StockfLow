<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Données de référence uniquement. Le jeu de démonstration se charge à part,
     * sur une base remise à zéro : php artisan migrate:fresh --seed --seeder=DemoSeeder
     * (jamais en production). Voir docs/04-database/initialisation.md.
     */
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);
    }
}
