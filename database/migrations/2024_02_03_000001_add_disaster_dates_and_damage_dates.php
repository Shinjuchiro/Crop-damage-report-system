<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Official disaster dates, actual crop damage dates, and the planting record
 * a damage report was built from.
 *
 * THE FLOW THIS SUPPORTS
 *
 * The MAO publishes a Disaster Advisory in Announcements with the hazard, the
 * event's name and the OFFICIAL DISASTER DATE. That date is saved as data on
 * a row of the existing disasters table (date_start), and the announcement
 * points at it (notification_broadcasts.disaster_id). The Crop Damage Report
 * form already lets a farmer pick a disaster event, so it now reads the date
 * from there and checks the farmer's dates against it:
 *
 *   planting date      BEFORE the disaster date
 *   crop damage date   ON OR AFTER the disaster date
 *
 * WHAT IS ADDED
 *
 * disasters.type             the new hazards (the same keys as damage causes),
 *                            so an advisory for a storm surge or a landslide
 *                            can create an event of that kind.
 * notification_broadcasts    hazard (which of the ten the advisory is about)
 *                            and disaster_id (the event it created).
 * damage_reports             date_damaged: the ACTUAL crop damage date the
 *                            farmer gives. submitted_at: when the report was
 *                            filed, kept apart from it. Back-filled from
 *                            created_at for reports that already exist.
 * damage_report_crops        crop_planting_record_crop_id: the Crop Planted
 *                            record the farmer picked, when they picked one.
 *                            Null means the date was typed by hand.
 *
 * Nothing existing is rewritten or removed. Reports whose dates disagree with
 * their event are not touched here either: that is worked out when they are
 * read (see App\Support\DateCheck) and shown to the MAO as a flag.
 */
return new class extends Migration
{
    private const NEW_TYPES = [
        'typhoon', 'flood', 'drought', 'strong_winds', 'other',
        'storm_surge', 'soil_erosion', 'landslide', 'saltwater_intrusion',
        'sea_level_rise', 'heat_stress', 'pest_disease_outbreak',
    ];

    private const OLD_TYPES = ['typhoon', 'flood', 'drought', 'strong_winds', 'other'];

    public function up(): void
    {
        DB::statement(
            "ALTER TABLE disasters MODIFY type ENUM('" . implode("','", self::NEW_TYPES) . "') NOT NULL"
        );

        Schema::table('notification_broadcasts', function (Blueprint $table) {
            if (! Schema::hasColumn('notification_broadcasts', 'hazard')) {
                $table->string('hazard', 40)->nullable()->after('category');
            }

            if (! Schema::hasColumn('notification_broadcasts', 'disaster_id')) {
                $table->foreignId('disaster_id')->nullable()->after('hazard')
                    ->constrained('disasters')->nullOnDelete();
            }
        });

        Schema::table('damage_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('damage_reports', 'date_damaged')) {
                $table->date('date_damaged')->nullable()->after('damage_cause_other');
            }

            if (! Schema::hasColumn('damage_reports', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('date_damaged');
            }
        });

        // Reports that already exist were submitted when they were created.
        DB::table('damage_reports')->whereNull('submitted_at')->update(['submitted_at' => DB::raw('created_at')]);

        Schema::table('damage_report_crops', function (Blueprint $table) {
            if (! Schema::hasColumn('damage_report_crops', 'crop_planting_record_crop_id')) {
                $table->foreignId('crop_planting_record_crop_id')->nullable()->after('crop_specify')
                    ->constrained('crop_planting_record_crops')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('damage_report_crops', function (Blueprint $table) {
            if (Schema::hasColumn('damage_report_crops', 'crop_planting_record_crop_id')) {
                $table->dropConstrainedForeignId('crop_planting_record_crop_id');
            }
        });

        Schema::table('damage_reports', function (Blueprint $table) {
            foreach (['submitted_at', 'date_damaged'] as $column) {
                if (Schema::hasColumn('damage_reports', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('notification_broadcasts', function (Blueprint $table) {
            if (Schema::hasColumn('notification_broadcasts', 'disaster_id')) {
                $table->dropConstrainedForeignId('disaster_id');
            }

            if (Schema::hasColumn('notification_broadcasts', 'hazard')) {
                $table->dropColumn('hazard');
            }
        });

        // Events of a type the old list does not have fall back to 'other'.
        DB::table('disasters')->whereNotIn('type', self::OLD_TYPES)->update(['type' => 'other']);

        DB::statement(
            "ALTER TABLE disasters MODIFY type ENUM('" . implode("','", self::OLD_TYPES) . "') NOT NULL"
        );
    }
};
