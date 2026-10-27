<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which linked disaster is the MAIN one for a damage report.
 *
 * A report can be linked to several disaster events, and until now nothing said
 * which of them caused the damage. The planting and damage dates are checked
 * against ONE disaster date, so when a report has several, the farmer (or the
 * office, when it corrects the links) marks one as the main event and the dates
 * are checked against that one.
 *
 * Optional on purpose: a report with a single event needs no marking, and a
 * report with several that has none marked is still accepted. It is flagged for
 * the MAO to review instead (see App\Support\DateCheck).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('damage_report_disasters', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('disaster_id');
        });
    }

    public function down(): void
    {
        Schema::table('damage_report_disasters', function (Blueprint $table) {
            $table->dropColumn('is_primary');
        });
    }
};
