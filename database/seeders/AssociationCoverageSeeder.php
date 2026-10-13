<?php

namespace Database\Seeders;

use App\Models\Association;
use App\Models\Barangay;
use Illuminate\Database\Seeder;

/**
 * Area coverage per farmers' association.
 *
 * Transcribed from the Municipal Agriculture Office's own sheet, "AREA
 * COVERAGE PER FARMER'S ASSOCIATION", which is the office's record of which
 * barangays each association operates in. This is what the map shades.
 *
 * Note that coverage is not the same as the office barangay already stored on
 * the associations table, and the two disagree on purpose: Tanza Farmers
 * Association has its office in Daang Amaya and covers seven barangays that do
 * not include it.
 *
 * TWO READINGS WORTH KNOWING ABOUT
 *
 * 1. The sheet spells it "Tres Cruses". The barangays table uses the PSGC
 *    spelling, "Tres Cruces", which is what the boundary file is keyed on.
 *    Same barangay.
 *
 * 2. The sheet lists "Amaya" for PHAMBBSS as a single entry, but Tanza has
 *    seven: Amaya I through VII. It is read here as all seven, because the
 *    association's own name expands every other entry to a specific barangay
 *    and there is no barangay called plain "Amaya" to point at. If the office
 *    means only some of them, correct it here and re-run; that is one line.
 *
 * Re-running is safe: syncing replaces each association's coverage rather than
 * adding to it, so an edit to the list below is reflected exactly.
 */
class AssociationCoverageSeeder extends Seeder
{
    /**
     * Association name => the barangays it covers, as the sheet lists them.
     * 'Amaya' expands to Amaya I-VII; see the note above.
     */
    private const COVERAGE = [
        'Tanza Rural & Urban Organic Farmers Association (TRUOFA)' => [
            'Halayhay', 'Calibuyo', 'Tres Cruces',
        ],

        'Tres Cruses Agrarian Reform Beneficiaries Farmers Association, Inc.' => [
            'Tres Cruces',
        ],

        'Calibuyo Farmers Association' => [
            'Calibuyo',
        ],

        'Calibuyo Lambingan Capipisa Tres Cruses (CALACATC) Rice Farmers Association' => [
            'Calibuyo', 'Lambingan', 'Capipisa', 'Tres Cruces',
        ],

        'Punta I & II Halayhay Amaya Mulawin Biga Bagtas Sanja Mayor Sahud Ulan (PHAMBBSS) Rice Farmers Association' => [
            'Punta I', 'Punta II', 'Halayhay', 'Amaya', 'Mulawin',
            'Biga', 'Bagtas', 'Sanja Mayor', 'Sahud Ulan',
        ],

        'Paradahan I Paradahan II Bunga Santol (P2BS) Rice Farmers Association' => [
            'Paradahan I', 'Paradahan II', 'Bunga', 'Santol',
        ],

        'Tanza Farmers Association' => [
            'Biga', 'Calibuyo', 'Capipisa', 'Tres Cruces',
            'Paradahan I', 'Punta II', 'Santol',
        ],

        'Gatas ng Tanza Farmers Association, Inc.' => [
            'Paradahan I', 'Bunga', 'Tres Cruces',
        ],
    ];

    /** The sheet's shorthand, and every barangay it stands for. */
    private const GROUPS = [
        'Amaya' => ['Amaya I', 'Amaya II', 'Amaya III', 'Amaya IV', 'Amaya V', 'Amaya VI', 'Amaya VII'],
    ];

    public function run(): void
    {
        $barangayIds = Barangay::pluck('id', 'name');

        foreach (self::COVERAGE as $associationName => $covered) {
            $association = Association::where('name', $associationName)->first();

            // A renamed association should be noticed, not silently skipped.
            if (! $association) {
                $this->command?->warn("Coverage skipped, no such association: {$associationName}");
                continue;
            }

            $ids = [];

            foreach ($covered as $name) {
                foreach (self::GROUPS[$name] ?? [$name] as $barangayName) {
                    if (isset($barangayIds[$barangayName])) {
                        $ids[] = $barangayIds[$barangayName];
                    } else {
                        $this->command?->warn("Coverage skipped, no such barangay: {$barangayName}");
                    }
                }
            }

            // sync, not attach: the list above is the whole truth about this
            // association's coverage, so anything not in it is removed.
            $association->coverage()->sync(array_unique($ids));
        }
    }
}
