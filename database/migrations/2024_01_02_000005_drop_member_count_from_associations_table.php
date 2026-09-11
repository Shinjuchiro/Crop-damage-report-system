<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Membership is not a number somebody types in. An association's membership is
 * the set of farmers who registered and chose it, so the count is derived from
 * the farmers table rather than stored and left to go stale.
 *
 * Guarded, because the column only exists on databases that ran the earlier
 * version of the previous migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('associations', 'member_count')) {
            Schema::table('associations', function (Blueprint $table) {
                $table->dropColumn('member_count');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('associations', 'member_count')) {
            Schema::table('associations', function (Blueprint $table) {
                $table->unsignedInteger('member_count')->nullable()->after('barangay_id');
            });
        }
    }
};
