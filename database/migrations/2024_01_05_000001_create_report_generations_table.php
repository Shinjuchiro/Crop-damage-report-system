<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A lightweight audit trail of "MAO generated the report for this
     * month/year" — not a cache of the report's contents. The figures
     * themselves are always recomputed live from the tables that already
     * exist (proposal section 76: reports must come from actual data), so
     * nothing here can go stale. This table only answers "has this period
     * been generated before, by whom, and when" for the Report History
     * list on the Reports page.
     */
    public function up(): void
    {
        Schema::create('report_generations', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month'); // 1-12
            $table->foreignId('generated_by')->constrained('users')->onDelete('restrict');
            $table->timestamp('generated_at');

            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_generations');
    }
};
