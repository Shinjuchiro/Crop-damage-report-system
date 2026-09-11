<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The PSGC code is the official Philippine Standard Geographic Code for each
 * barangay. Storing it lets the map join our records to the government boundary
 * file by code instead of by name, so a spelling difference never breaks it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barangays', function (Blueprint $table) {
            $table->string('psgc_code', 12)->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('barangays', function (Blueprint $table) {
            $table->dropUnique(['psgc_code']);
            $table->dropColumn('psgc_code');
        });
    }
};
