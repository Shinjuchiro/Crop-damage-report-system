<?php

namespace Database\Seeders;

use App\Models\Association;
use App\Models\Barangay;
use Illuminate\Database\Seeder;

/**
 * The 8 farmers associations of Tanza, Cavite, as listed by the Municipal
 * Agriculture Office. Registration requires selecting one, so these must exist
 * before any farmer can register.
 *
 * 'office' is the barangay the association's office is in, which is where the
 * map places it. Membership is not seeded: it comes from the farmers who
 * register and choose the association.
 */
class AssociationSeeder extends Seeder
{
    public function run(): void
    {
        $associations = [
            [
                'name'    => 'Tanza Rural & Urban Organic Farmers Association (TRUOFA)',
                'office'  => 'Sahud Ulan',
            ],
            [
                'name'    => 'Tres Cruses Agrarian Reform Beneficiaries Farmers Association, Inc.',
                'office'  => 'Tres Cruces',
            ],
            [
                'name'    => 'Calibuyo Farmers Association',
                'office'  => 'Calibuyo',
            ],
            [
                'name'    => 'Calibuyo Lambingan Capipisa Tres Cruses (CALACATC) Rice Farmers Association',
                'office'  => 'Calibuyo',
            ],
            [
                'name'    => 'Punta I & II Halayhay Amaya Mulawin Biga Bagtas Sanja Mayor Sahud Ulan (PHAMBBSS) Rice Farmers Association',
                'office'  => 'Punta I',
            ],
            [
                'name'    => 'Paradahan I Paradahan II Bunga Santol (P2BS) Rice Farmers Association',
                'office'  => 'Santol',
            ],
            [
                'name'    => 'Tanza Farmers Association',
                'office'  => 'Daang Amaya I',
            ],
            [
                'name'    => 'Gatas ng Tanza Farmers Association, Inc.',
                'office'  => 'Paradahan I',
            ],
        ];

        $barangays = Barangay::pluck('id', 'name');

        foreach ($associations as $association) {
            Association::updateOrCreate(
                ['name' => $association['name']],
                [
                    'location'    => $association['office'],
                    'barangay_id' => $barangays[$association['office']] ?? null,
                ]
            );
        }
    }
}
