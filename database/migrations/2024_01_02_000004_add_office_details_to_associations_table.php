<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The map aggregates damage by association, so each association needs a place
 * on the map: the barangay where its office sits.
 *
 * Membership is deliberately NOT stored here. How many farmers belong to an
 * association is answered by counting the farmers who registered and chose it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('associations', function (Blueprint $table) {
            $table->foreignId('barangay_id')->nullable()->after('location')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('associations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('barangay_id');
        });
    }
};
