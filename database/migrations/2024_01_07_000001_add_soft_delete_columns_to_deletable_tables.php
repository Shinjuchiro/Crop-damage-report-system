<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Delete Permanently" on the Archive page (proposal section 91.10) used to
 * be a real SQL DELETE for Crops/Disasters/Associations/Assistance, and for
 * Farmers/Users/Alerts it deleted the row (or, for Farmers, the login
 * account, which cascaded to the farmer profile). The developer asked that
 * a deleted record's data be kept either way, along with who deleted it.
 *
 * So every one of the seven Archive page tabs gets the same soft-delete pair
 * deleted_at/deleted_by, exactly parallel to archived_at/archived_by
 * (migration 2024_01_04_000001) for Associations/Crops/Disasters. See
 * app/Models/Concerns/SoftDeletable.php for how these columns are used, and
 * MAO/ArchiveController.php for the read-only "Deleted" tab that lists them.
 */
return new class extends Migration
{
    private const TABLES = [
        'farmers', 'associations', 'users', 'crops', 'disasters',
        'assistances', 'notification_broadcasts',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'deleted_at')) {
                    $blueprint->timestamp('deleted_at')->nullable()->after('id');
                }

                if (! Schema::hasColumn($table, 'deleted_by')) {
                    $blueprint->foreignId('deleted_by')->nullable()->after('deleted_at')
                        ->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::hasColumn($table, 'deleted_by')) {
                    $blueprint->dropConstrainedForeignId('deleted_by');
                }

                if (Schema::hasColumn($table, 'deleted_at')) {
                    $blueprint->dropColumn('deleted_at');
                }
            });
        }
    }
};
