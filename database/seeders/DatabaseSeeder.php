<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Users and pricing settings are always seeded (idempotent). Sample
     * catalogue and jobs only in local/testing, never in production.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            SettingsSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call([
                SampleCatalogSeeder::class,
                SampleJobSeeder::class,
            ]);
        }
    }
}
