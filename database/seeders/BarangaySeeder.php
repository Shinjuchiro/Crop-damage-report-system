<?php

namespace Database\Seeders;

use App\Models\Barangay;
use Illuminate\Database\Seeder;

/**
 * The 41 barangays of Tanza, Cavite, each with its official PSGC code.
 *
 * The codes match the boundary file at public/geo/tanza-barangays.json, which
 * is what lets the map colour each barangay without matching on spelling.
 */
class BarangaySeeder extends Seeder
{
    public function run(): void
    {
        $barangays = [
            ['Amaya I', 'PH042120001'],
            ['Amaya II', 'PH042120028'],
            ['Amaya III', 'PH042120029'],
            ['Amaya IV', 'PH042120030'],
            ['Amaya V', 'PH042120031'],
            ['Amaya VI', 'PH042120032'],
            ['Amaya VII', 'PH042120033'],
            ['Bagtas', 'PH042120002'],
            ['Biga', 'PH042120003'],
            ['Biwas', 'PH042120004'],
            ['Bucal', 'PH042120005'],
            ['Bunga', 'PH042120006'],
            ['Calibuyo', 'PH042120007'],
            ['Capipisa', 'PH042120008'],
            ['Daang Amaya I', 'PH042120009'],
            ['Daang Amaya II', 'PH042120034'],
            ['Daang Amaya III', 'PH042120035'],
            ['Halayhay', 'PH042120010'],
            ['Julugan I', 'PH042120011'],
            ['Julugan II', 'PH042120036'],
            ['Julugan III', 'PH042120037'],
            ['Julugan IV', 'PH042120038'],
            ['Julugan V', 'PH042120039'],
            ['Julugan VI', 'PH042120040'],
            ['Julugan VII', 'PH042120041'],
            ['Julugan VIII', 'PH042120042'],
            ['Lambingan', 'PH042120027'],
            ['Mulawin', 'PH042120012'],
            ['Paradahan I', 'PH042120015'],
            ['Paradahan II', 'PH042120043'],
            ['Poblacion I', 'PH042120016'],
            ['Poblacion II', 'PH042120017'],
            ['Poblacion III', 'PH042120018'],
            ['Poblacion IV', 'PH042120019'],
            ['Punta I', 'PH042120020'],
            ['Punta II', 'PH042120044'],
            ['Sahud Ulan', 'PH042120021'],
            ['Sanja Mayor', 'PH042120022'],
            ['Santol', 'PH042120023'],
            ['Tanauan', 'PH042120024'],
            ['Tres Cruces', 'PH042120026'],
        ];

        foreach ($barangays as [$name, $psgc]) {
            Barangay::updateOrCreate(['name' => $name], ['psgc_code' => $psgc]);
        }
    }
}
