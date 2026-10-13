<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            MaoAdminSeeder::class,
            BarangaySeeder::class,
            AssociationSeeder::class,
            // After AssociationSeeder: coverage is keyed on association names,
            // so the associations have to exist first.
            AssociationCoverageSeeder::class,
            CropSeeder::class,
        ]);
    }
}
