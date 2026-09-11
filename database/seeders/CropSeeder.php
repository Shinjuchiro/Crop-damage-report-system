<?php

namespace Database\Seeders;

use App\Models\Crop;
use Illuminate\Database\Seeder;

class CropSeeder extends Seeder
{
    public function run(): void
    {
        Crop::firstOrCreate(['name' => 'Rice'], ['is_hvcc' => false]);
        Crop::firstOrCreate(['name' => 'Corn'], ['is_hvcc' => false]);
        Crop::firstOrCreate(['name' => 'HVCC'], ['is_hvcc' => true]);
        // MAO can add more crop types later through the Crop Management screen.
    }
}
