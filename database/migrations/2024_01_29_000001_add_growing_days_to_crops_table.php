<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The system never recorded an expected harvest date. Rather than ask the
 * farmer for one, the office gives each crop its usual growing period in
 * days, and the monitoring pages show "date planted + growing days" as an
 * ESTIMATED harvest date. Left empty for a crop with no sensible single
 * figure (HVCC covers many different crops), in which case the pages say
 * the estimate is not available.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('crops', 'growing_days')) {
            Schema::table('crops', function (Blueprint $table) {
                $table->unsignedSmallInteger('growing_days')->nullable()->after('is_hvcc');
            });
        }

        // Usual figures for the two main crops, only where nothing is set.
        DB::table('crops')->whereRaw('LOWER(name) = ?', ['rice'])->whereNull('growing_days')->update(['growing_days' => 120]);
        DB::table('crops')->whereRaw('LOWER(name) = ?', ['corn'])->whereNull('growing_days')->update(['growing_days' => 100]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('crops', 'growing_days')) {
            Schema::table('crops', function (Blueprint $table) {
                $table->dropColumn('growing_days');
            });
        }
    }
};
