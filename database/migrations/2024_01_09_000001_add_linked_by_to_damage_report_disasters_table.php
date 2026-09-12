<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a technician link (or correct) a disaster event on a report during
 * their own field inspection, instead of only MAO or the farmer ever being
 * able to touch damage_report_disasters.
 *
 * The developer's own framing: a farmer might file before the office has
 * declared the event, or might pick the wrong one from the list. The
 * technician is standing on the farm and often knows better by the time
 * they inspect it, so their own inspection step is where a correction
 * naturally belongs - not a separate MAO screen.
 *
 * linked_by / linked_by_role record who attached THIS PARTICULAR disaster
 * to THIS PARTICULAR report, and with what role, so a screen can show
 * "Farmer" vs "Technician - Juan dela Cruz" next to each one instead of
 * the pivot row being anonymous. The technician's inspection step can
 * both add new links and remove ones the farmer got wrong (the developer
 * explicitly wanted "add or correct anytime", not just fill gaps), so this
 * is deliberately NOT append-only: a removed row is gone from the pivot,
 * with the change explained in audit_logs instead of preserved here.
 *
 * Existing rows (all farmer-declared, from before this feature existed)
 * are backfilled to linked_by_role = 'farmer' with linked_by left null,
 * since there is no per-row user reliably attributable after the fact -
 * the UI shows a plain "Farmer" tag for those instead of a name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('damage_report_disasters', function (Blueprint $table) {
            $table->foreignId('linked_by')->nullable()->after('disaster_id')
                ->constrained('users')->nullOnDelete();
            $table->string('linked_by_role', 20)->nullable()->after('linked_by');
            $table->timestamp('created_at')->nullable()->after('linked_by_role');

            // A report is only ever linked to the same disaster once.
            $table->unique(['damage_report_id', 'disaster_id'], 'drd_report_disaster_unique');
        });

        DB::table('damage_report_disasters')
            ->whereNull('linked_by_role')
            ->update(['linked_by_role' => 'farmer']);
    }

    public function down(): void
    {
        Schema::table('damage_report_disasters', function (Blueprint $table) {
            $table->dropUnique('drd_report_disaster_unique');
            $table->dropConstrainedForeignId('linked_by');
            $table->dropColumn(['linked_by_role', 'created_at']);
        });
    }
};
