<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section 72: important records use Archive instead of permanent deletion.
 *
 * Associations, crops and disasters could previously only be hard deleted,
 * and only when nothing referenced them yet. That left no way to retire a
 * crop that farmers had already used, or an association that no longer
 * operates, without either deleting real history or leaving stale entries
 * cluttering every dropdown forever. archived_at plus archived_by gives each
 * of the three a soft, reversible "take this out of circulation" action,
 * shown from the new MAO Archive page.
 *
 * Farmers, User accounts (technicians and association officers) and Alerts
 * already have their own status column doing this job (see
 * MembershipApplicationController::archive(), UserManagementController::
 * updateStatus(), NotificationBroadcastController::archive()). Assistance
 * already has a status column with an 'inactive' value. Those five are not
 * touched here.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['associations', 'crops', 'disasters'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'archived_at')) {
                    $blueprint->timestamp('archived_at')->nullable()->after('id');
                }

                if (! Schema::hasColumn($table, 'archived_by')) {
                    $blueprint->foreignId('archived_by')->nullable()->after('archived_at')
                        ->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['associations', 'crops', 'disasters'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::hasColumn($table, 'archived_by')) {
                    $blueprint->dropConstrainedForeignId('archived_by');
                }

                if (Schema::hasColumn($table, 'archived_at')) {
                    $blueprint->dropColumn('archived_at');
                }
            });
        }
    }
};
