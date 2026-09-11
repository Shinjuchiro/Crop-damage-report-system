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
            CropSeeder::class,
        ]);
    }
}
