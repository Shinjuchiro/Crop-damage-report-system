<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Damage reports are numbered per farmer (DR-0001, DR-0002 ...), and the
 * farmer's own ID (FARM-005) is the only thing that is unique on its own.
 *
 * The number is stored, not counted, so it never shifts when a report is
 * archived or deleted. Existing reports are numbered by the order each
 * farmer filed them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->unsignedInteger('farmer_report_no')->nullable()->after('farmer_id');
        });

        $next = [];

        DB::table('damage_reports')->orderBy('id')->select('id', 'farmer_id')->each(function ($row) use (&$next) {
            $next[$row->farmer_id] = ($next[$row->farmer_id] ?? 0) + 1;

            DB::table('damage_reports')->where('id', $row->id)
                ->update(['farmer_report_no' => $next[$row->farmer_id]]);
        });

        Schema::table('damage_reports', function (Blueprint $table) {
            $table->unique(['farmer_id', 'farmer_report_no'], 'damage_reports_farmer_no_unique');
        });
    }

    public function down(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->dropUnique('damage_reports_farmer_no_unique');
            $table->dropColumn('farmer_report_no');
        });
    }
};
