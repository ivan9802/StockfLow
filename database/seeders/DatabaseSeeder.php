<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Données de référence uniquement. Le jeu de démonstration se charge à part :
     * php artisan db:seed --class=DemoSeeder (jamais en production).
     */
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);
    }
}
