<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds shot_type to validation_photos.
 *
 * The field inspection asks for two specific shots (proposal section 43 and
 * the approved mockup): a wide shot showing the whole affected area, and a
 * close-up showing the actual condition of the plants. Before this column a
 * photo was just "a photo", so nobody looking at the record afterwards could
 * tell which was which.
 *
 *   wide     the whole field, taken from a distance
 *   closeup  the damaged plants themselves
 *   other    any extra photo the technician chose to add
 *
 * Existing rows become 'other', which is true: they were uploaded before the
 * two named slots existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('validation_photos', function (Blueprint $table) {
            if (! Schema::hasColumn('validation_photos', 'shot_type')) {
                $table->enum('shot_type', ['wide', 'closeup', 'other'])
                      ->default('other')
                      ->after('validation_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('validation_photos', function (Blueprint $table) {
            if (Schema::hasColumn('validation_photos', 'shot_type')) {
                $table->dropColumn('shot_type');
            }
        });
    }
};
