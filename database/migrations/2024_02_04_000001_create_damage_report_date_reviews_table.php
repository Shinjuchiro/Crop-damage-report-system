<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The MAO's review of a damage report whose dates disagree with the official
 * disaster date (see App\Support\DateCheck).
 *
 * The disagreement itself is never stored: it is worked out from the report's
 * dates and the disaster record every time it is shown. What is stored here is
 * the office's DECISION about it: who looked, when, any remark, and a snapshot
 * of the reasons that were on screen at that moment.
 *
 * Reviews are only ever added, never edited, so a report keeps its full review
 * history. Neither the report, its planting date nor the planting record is
 * touched by a review.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_report_date_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('damage_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            // The plain-language reasons that were shown when it was reviewed (JSON list).
            $table->text('flags_snapshot');
            $table->date('official_date')->nullable();
            $table->timestamp('reviewed_at')->useCurrent();

            $table->index(['damage_report_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_report_date_reviews');
    }
};
