<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Damage causes: the ten CRVA hazards, and the typhoon's name.
 *
 * damage_reports.damage_cause is a MySQL ENUM, so a new cause cannot be saved
 * until the column lists it. This adds the six hazards that were not already
 * a choice (storm surge, soil erosion, landslide, saltwater intrusion, sea
 * level rise, and pest and disease outbreak). Typhoon, flood, drought and
 * extreme heat (stored as heat_stress) were already there.
 *
 * pest_infestation and plant_disease stay in the list so reports filed under
 * them keep their cause; the form no longer offers them, because Pest and
 * Disease Outbreak covers both.
 *
 * typhoon_name holds the name a farmer gives when the cause is a typhoon
 * (for example "Egay"). Nullable, because every other cause has none and
 * because reports filed before this existed have no name.
 */
return new class extends Migration
{
    private const NEW_LIST = [
        'typhoon', 'flood', 'drought', 'strong_winds',
        'pest_infestation', 'plant_disease', 'heat_stress', 'other',
        'storm_surge', 'soil_erosion', 'landslide', 'saltwater_intrusion',
        'sea_level_rise', 'pest_disease_outbreak',
    ];

    private const OLD_LIST = [
        'typhoon', 'flood', 'drought', 'strong_winds',
        'pest_infestation', 'plant_disease', 'heat_stress', 'other',
    ];

    public function up(): void
    {
        DB::statement(
            "ALTER TABLE damage_reports MODIFY damage_cause ENUM('" . implode("','", self::NEW_LIST) . "') NULL"
        );

        if (! Schema::hasColumn('damage_reports', 'typhoon_name')) {
            Schema::table('damage_reports', function (Blueprint $table) {
                $table->string('typhoon_name', 120)->nullable()->after('damage_cause_other');
            });
        }
    }

    public function down(): void
    {
        // A report saved under one of the new causes would not fit the old
        // list, so those fall back to 'other' rather than failing the rollback.
        DB::table('damage_reports')
            ->whereNotIn('damage_cause', self::OLD_LIST)
            ->update(['damage_cause' => 'other']);

        DB::statement(
            "ALTER TABLE damage_reports MODIFY damage_cause ENUM('" . implode("','", self::OLD_LIST) . "') NULL"
        );

        if (Schema::hasColumn('damage_reports', 'typhoon_name')) {
            Schema::table('damage_reports', function (Blueprint $table) {
                $table->dropColumn('typhoon_name');
            });
        }
    }
};
