<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which barangays each farmers' association covers.
 *
 * The associations table already has barangay_id, but that is one barangay and
 * it means something else: where the association's OFFICE sits. The MAO's own
 * "Area Coverage per Farmer's Association" sheet lists a second thing, the
 * barangays each association actually operates in, and there are up to nine of
 * them. PHAMBBSS has its office in Punta I and works across nine barangays;
 * Tanza Farmers Association has its office in Daang Amaya I, which is not in
 * its coverage at all.
 *
 * The two overlap heavily in the other direction as well: Tres Cruces is
 * covered by five of the eight associations and Calibuyo by four. That rules
 * out a column on either table and makes this a plain many-to-many.
 *
 * No timestamps: a row here is a fact about jurisdiction, not an event, and
 * the office re-states the whole coverage when it changes rather than adding
 * to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('association_barangay', function (Blueprint $table) {
            $table->id();

            $table->foreignId('association_id')
                ->constrained('associations')
                ->cascadeOnDelete();

            $table->foreignId('barangay_id')
                ->constrained('barangays')
                ->cascadeOnDelete();

            // One row per pair. Saves the seeder from having to care whether
            // it has run before, and stops a double-submitted edit form from
            // shading the same barangay twice.
            $table->unique(['association_id', 'barangay_id']);

            // The map asks "which barangays does this association cover" far
            // more often than the reverse, so the association leads.
            $table->index('barangay_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('association_barangay');
    }
};
