<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Assistance Distribution (Oct 2026 revision).
 *
 * A batch now has a name, a free-text description of what is being given, an
 * optional distribution date and an optional venue. Nothing here is an
 * amount, a budget or a formula: the description is plain text on purpose
 * (see App\Models\Assistance for the privacy decision behind that).
 *
 * The date and venue are nullable because the office often does not know
 * them when the assistance arrives. They are filled in later from the batch's
 * own page, which notifies the farmers and associations at that point.
 *
 * Drafts live in their own table rather than as a status on the allocation.
 * Almost every screen counts allocations "where status is not cancelled", and
 * a half-finished form must not make a farmer disappear from the eligible
 * list or appear on an association's page. A separate table cannot leak into
 * any of that by construction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistance_allocations', function (Blueprint $table) {
            $table->string('batch_name', 150)->nullable()->after('id');
            $table->text('details')->nullable()->after('batch_name');
            $table->date('distribution_date')->nullable()->after('details');
            $table->string('venue', 255)->nullable()->after('distribution_date');
        });

        Schema::create('assistance_allocation_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('batch_name', 150)->nullable();
            $table->text('details')->nullable();
            $table->date('distribution_date')->nullable();
            $table->string('venue', 255)->nullable();
            // Farmer ids ticked so far. Re-checked against live eligibility
            // when the draft is opened and again when it is finalized.
            $table->json('farmer_ids')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistance_allocation_drafts');

        Schema::table('assistance_allocations', function (Blueprint $table) {
            $table->dropColumn(['batch_name', 'details', 'distribution_date', 'venue']);
        });
    }
};
