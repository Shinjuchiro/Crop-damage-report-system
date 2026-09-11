<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds damage_cause to damage_reports.
 *
 * WHY THIS EXISTS
 *
 * Until now a damage report had to be tied to a disaster event, and only the
 * MAO can create those. That works for a typhoon: the office declares
 * "Typhoon Kristine", farmers report against it.
 *
 * It does not work for the rest of what actually ruins a crop. Army worm in
 * the corn, rice black bug, a fungal disease, or a week of extreme heat are
 * not events anybody declares. A farmer losing a hectare to insects had no
 * way to file a report at all, and had to wait for the office to invent a
 * disaster record first.
 *
 * So the cause of the damage now lives on the report itself and is always
 * answerable. Linking to a declared event stays, but it is now extra
 * information rather than the gate.
 *
 * Nullable in the database so existing reports do not break. It is required
 * in the form, which is where new reports come from.
 */
return new class extends Migration
{
    /** Causes that correspond to a declared weather event. */
    private const WEATHER_CAUSES = ['typhoon', 'flood', 'drought', 'strong_winds'];

    public function up(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('damage_reports', 'damage_cause')) {
                $table->enum('damage_cause', [
                    'typhoon', 'flood', 'drought', 'strong_winds',
                    'pest_infestation', 'plant_disease', 'heat_stress', 'other',
                ])->nullable()->after('farmer_id');
            }

            // Free text, only used when the cause is "other". Keeps the enum
            // clean instead of letting people type anything into it.
            if (! Schema::hasColumn('damage_reports', 'damage_cause_other')) {
                $table->string('damage_cause_other')->nullable()->after('damage_cause');
            }
        });

        $this->backfill();
    }

    /**
     * Fill in the cause for reports that were made before this column existed.
     *
     * A report already had at least one disaster attached, so the type of that
     * disaster is the honest answer. Written as a loop rather than a joined
     * UPDATE so it does not depend on MySQL specific syntax.
     */
    private function backfill(): void
    {
        if (! Schema::hasColumn('damage_reports', 'damage_cause')) {
            return;
        }

        $ids = DB::table('damage_reports')->whereNull('damage_cause')->pluck('id');

        foreach ($ids as $id) {
            $type = DB::table('damage_report_disasters')
                ->join('disasters', 'disasters.id', '=', 'damage_report_disasters.disaster_id')
                ->where('damage_report_disasters.damage_report_id', $id)
                ->value('disasters.type');

            DB::table('damage_reports')->where('id', $id)->update([
                'damage_cause' => in_array($type, self::WEATHER_CAUSES, true) ? $type : 'other',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            foreach (['damage_cause', 'damage_cause_other'] as $column) {
                if (Schema::hasColumn('damage_reports', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
