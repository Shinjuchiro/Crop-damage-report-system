<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Association module's own Reports page (BUILD-STATUS, Association
 * Reports build) reuses the same report_generations audit trail as MAO's
 * Reports page, but an association only ever generates its own scoped
 * periods and must never see MAO's office-wide history mixed in, or vice
 * versa. A nullable association_id tags which rows belong to which list:
 * null means "MAO's office-wide report", set means "this association's own
 * scoped report". Deleting an association keeps its historical
 * report_generations rows (set null) rather than deleting the audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_generations', function (Blueprint $table) {
            $table->foreignId('association_id')
                ->nullable()
                ->after('id')
                ->constrained('associations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('report_generations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('association_id');
        });
    }
};
