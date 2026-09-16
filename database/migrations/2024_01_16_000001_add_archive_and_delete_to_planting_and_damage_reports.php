<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sept 2026: crop planting records and damage reports join the Archive page
 * as two more tabs, so MAO can retire a mistaken/duplicate submission
 * without touching every other record type's own workflow (a damage
 * report's own status column - pending through approved/rejected - is
 * untouched by this; a report can be archived at any point in that
 * pipeline, same as the developer asked for).
 *
 * Same archived_at/archived_by + deleted_at/deleted_by pair as migrations
 * 2024_01_04_000001 and 2024_01_07_000001 gave Associations/Crops/Disasters,
 * so both new types can reuse Archivable/SoftDeletable unchanged.
 */
return new class extends Migration
{
    private const TABLES = ['crop_planting_records', 'damage_reports'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'archived_at')) {
                    $blueprint->timestamp('archived_at')->nullable()->after('id');
                }

                if (! Schema::hasColumn($table, 'archived_by')) {
                    $blueprint->foreignId('archived_by')->nullable()->after('archived_at')
                        ->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn($table, 'deleted_at')) {
                    $blueprint->timestamp('deleted_at')->nullable()->after('archived_by');
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
                foreach (['deleted_by', 'archived_by'] as $fk) {
                    if (Schema::hasColumn($table, $fk)) {
                        $blueprint->dropConstrainedForeignId($fk);
                    }
                }

                foreach (['deleted_at', 'archived_at'] as $col) {
                    if (Schema::hasColumn($table, $col)) {
                        $blueprint->dropColumn($col);
                    }
                }
            });
        }
    }
};
