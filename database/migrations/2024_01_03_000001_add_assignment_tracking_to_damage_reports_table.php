<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds assigned_at to damage_reports.
 *
 * Why this is needed: the technician dashboard has an "Assigned Today" tile,
 * and there was no honest way to fill it in. updated_at changes every time
 * anything on the report changes, so it cannot answer "when was this handed
 * to a technician". This column records that one moment and nothing else.
 *
 * Written with hasColumn guards so running it twice does not break anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('damage_reports', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('assigned_technician_id');
            }
        });

        // Backfill for reports that were already assigned before this column
        // existed. updated_at is the closest thing we have to the moment of
        // assignment for those rows. New rows get the real value.
        if (Schema::hasColumn('damage_reports', 'assigned_at')) {
            DB::table('damage_reports')
                ->whereNotNull('assigned_technician_id')
                ->whereNull('assigned_at')
                ->update(['assigned_at' => DB::raw('updated_at')]);
        }
    }

    public function down(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            if (Schema::hasColumn('damage_reports', 'assigned_at')) {
                $table->dropColumn('assigned_at');
            }
        });
    }
};
