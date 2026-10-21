<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MAKE last_activity_date MEAN ONE THING (revision section 15, Oct 2026)
 *
 * Section 15 is specific about what moves the inactivity clock:
 *
 *   "A farmer becomes inactive after seven consecutive calendar months
 *    without a new valid Crop Planted Report."
 *   "If the farmer has never submitted a valid Crop Planted Report, use the
 *    registration date as the starting point."
 *   "Only a new valid Crop Planted Report resets the inactivity period."
 *
 * The column could not express that, for a reason that is easy to miss:
 * `$table->date('last_activity_date')` is NOT NULL. There was nowhere to
 * record "this farmer has never filed one", so something had to be written
 * at registration, and once a value had to exist, two things ended up
 * writing it:
 *
 *   Auth\RegisterController           the registration date
 *   MAO\MembershipApplicationController::approve()   the approval date
 *
 * Neither is a Crop Planted Report. The second is the one that matters:
 * approval silently restarted the clock, so a farmer who registered in
 * January, was approved in March and never planted anything was measured
 * from March. Section 15 says measure from January. The drift is however
 * long the office took to approve, which is exactly the stretch the rule
 * wants counted.
 *
 * It also made the column dishonest on screen. Five views label it "Last
 * Activity" and show the date, so a farmer who has never done anything had a
 * recent-looking activity date, and `months_inactive` read 0 for them
 * forever.
 *
 * SO: nullable, and NULL means "has never filed a Crop Planted Report". The
 * model falls back to the registration date for the arithmetic, which is what
 * section 15 asks for, and the screens say so in words.
 *
 * THE BACKFILL IS DERIVED, NOT GUESSED
 *
 * Unlike 2024_01_23_000001, which deliberately backfilled nothing because the
 * answer was unknowable, this one can be computed exactly. Every farmer's
 * real last planting report is in crop_planting_records, so the column is
 * rebuilt from it:
 *
 *   last_activity_date = MAX(date_submitted) of that farmer's planting
 *                        records, or NULL if they have none
 *
 * which is section 15's "Display the correct status based on actual records
 * and dates", applied to the stored value rather than only to what is shown.
 *
 * Archived and deleted planting records are excluded, matching
 * CropPlantingRecord's own Archivable/SoftDeletable scopes: a record the
 * office has retired should not keep a farmer looking active.
 *
 * WHAT THIS CHANGES FOR EXISTING ROWS
 *
 * A farmer with planting records gets the date of their real latest one,
 * which is usually what was already there. A farmer with none gets NULL, and
 * their inactivity is measured from registration from now on - so some
 * farmers who read Active today will correctly read Inactive after this runs.
 * That is the point of the change rather than a side effect of it, and it is
 * worth knowing before running it on the office's own data.
 *
 * Nothing is deleted and no planting record is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->date('last_activity_date')->nullable()->change();
        });

        /* Rebuild from the real records.
         *
         * One UPDATE with a correlated subquery rather than a PHP loop over
         * every farmer: this runs on the office's live database and a loop
         * would be one query per farmer plus one save per farmer. The
         * subquery returns NULL when a farmer has no qualifying record, which
         * is exactly the value wanted, so there is no second statement for
         * the null case.
         *
         * Written with the query builder's raw update rather than Eloquent so
         * that no model event, observer or accessor can intervene: this is a
         * data correction, not an application action. */
        DB::statement('
            UPDATE farmers
            SET last_activity_date = (
                SELECT MAX(cpr.date_submitted)
                FROM crop_planting_records cpr
                WHERE cpr.farmer_id = farmers.id
                  AND cpr.archived_at IS NULL
                  AND cpr.deleted_at IS NULL
            )
        ');
    }

    public function down(): void
    {
        /* Going back needs a value for every row, because the column becomes
           NOT NULL again. The registration date is the honest filler: it is
           what RegisterController used to write, and it is the date the model
           now falls back to anyway, so nothing reading the column changes
           meaning on the way back.

           Done before the column change, while NULLs are still allowed. */
        DB::statement('
            UPDATE farmers
            SET last_activity_date = DATE(created_at)
            WHERE last_activity_date IS NULL
        ');

        Schema::table('farmers', function (Blueprint $table) {
            $table->date('last_activity_date')->nullable(false)->change();
        });
    }
};
