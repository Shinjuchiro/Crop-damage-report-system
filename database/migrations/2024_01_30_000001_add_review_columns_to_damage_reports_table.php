<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The office's review of a technician's submitted assessment (Oct 2026).
 *
 * approved_by / approved_at already record an approval. A REJECTION had
 * nowhere to go: its remarks lived only in the audit log, so the page could
 * not show why. These three columns record either decision, and the remarks
 * (required when rejecting) stay with the report.
 *
 * Nothing existing is rewritten. Old approvals keep approved_by/approved_at;
 * the new columns are simply empty for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->foreignId('reviewed_by')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_remarks')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['reviewed_at', 'review_remarks']);
        });
    }
};
