<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the FARMER-reported location to damage_reports.
 *
 * Why this migration exists:
 * Proposal sections 37 and 49 say the system has to keep TWO locations
 * for every damage report, and that the farmer's one must never be
 * overwritten.
 *
 *   Farmer-reported location     -> these new columns
 *   Technician-verified location -> already in the validations table
 *
 * Before this, only the technician's coordinates were stored. That meant
 * a report had no location at all until somebody inspected it, and there
 * was nothing to compare the inspection against, which is the whole point
 * of keeping both.
 *
 * All the columns are nullable on purpose. A farmer standing in a flooded
 * field with no signal, or using an older phone with no GPS, still has to
 * be able to submit. In that case they describe the location in words in
 * farm_location_description instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {

            // hasColumn() checks are here so this migration can be run again
            // safely if something failed halfway through the first time.
            if (! Schema::hasColumn('damage_reports', 'reported_barangay_id')) {
                $table->foreignId('reported_barangay_id')
                    ->nullable()
                    ->after('farm_location_description')
                    ->constrained('barangays')
                    // If a barangay row is ever removed we do not want to lose
                    // the whole damage report, so just blank the link.
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('damage_reports', 'reported_latitude')) {
                // decimal(10, 7) means 10 digits total with 7 after the point,
                // which is about a centimetre of precision. That is far more
                // than a phone GPS can give, so we will never lose accuracy
                // by rounding. We use decimal instead of float because float
                // can drift slightly and coordinates should be exact.
                $table->decimal('reported_latitude', 10, 7)->nullable()->after('reported_barangay_id');
                $table->decimal('reported_longitude', 10, 7)->nullable()->after('reported_latitude');
            }

            if (! Schema::hasColumn('damage_reports', 'location_source')) {
                // How the coordinates were obtained. Without this, nobody
                // later can tell a real GPS reading from numbers that were
                // typed in by hand, and those are not the same thing.
                $table->enum('location_source', ['gps', 'manual', 'none'])
                    ->default('none')
                    ->after('reported_longitude');
            }
        });
    }

    /**
     * Undo everything, in case we need to roll back.
     */
    public function down(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {

            // The foreign key has to be dropped before the column itself,
            // which is what dropConstrainedForeignId() does in one step.
            if (Schema::hasColumn('damage_reports', 'reported_barangay_id')) {
                $table->dropConstrainedForeignId('reported_barangay_id');
            }

            // Only drop the columns that actually exist, so rolling back a
            // partly applied migration does not throw an error.
            $drop = array_filter(
                ['reported_latitude', 'reported_longitude', 'location_source'],
                fn ($column) => Schema::hasColumn('damage_reports', $column)
            );

            if ($drop) {
                $table->dropColumn($drop);
            }
        });
    }
};
