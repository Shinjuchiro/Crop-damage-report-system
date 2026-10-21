<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The office's assistance decision on one damage report.
 *
 * WHY THIS TABLE EXISTS
 *
 * Revision section 11.1 asks for exactly four main assistance statuses:
 * Pending Review, Designated for Assistance, Not Designated for Assistance,
 * and Received. Three of those four could already be worked out from data
 * the system keeps: a verified report with no decision is Pending Review, a
 * row in assistance_allocation_beneficiaries means Designated, and a
 * distribution the farmer has confirmed means Received.
 *
 * "Not Designated" had nowhere to live. A farmer the office decided against
 * simply had no row anywhere, which is indistinguishable from a farmer
 * nobody has looked at yet. The office needs to be able to say no, and a
 * farmer is entitled to have that answer recorded rather than being left
 * in a queue forever.
 *
 * ONE DECISION PER REPORT, AND WHY NOT A COLUMN
 *
 * The unique key is damage_report_id: one report is one farmer's case for
 * one event, which is the unit the office reviews and the unit the
 * beneficiary list is already keyed on. A decision can be changed (the
 * office may have been waiting on a document), and the row is updated in
 * place when that happens; audit_logs carries the history of who changed
 * what, as it does for every other decision in the system.
 *
 * This is a table rather than three columns on damage_reports because an
 * assistance decision is not a property of the damage report. Section 10.5
 * is explicit that the farmer's report, the inspection, the MAO decision on
 * the report, and the assistance designation are distinct stages, and
 * stages that are distinct in the workflow should not share a row.
 *
 * NOT the same thing as the damage report's own status. A report can be
 * approved (the office accepts the damage is real) and still be Not
 * Designated for assistance (there was not enough to go round). Section
 * 11.2 says so directly: assistance is never automatic just because a
 * report was submitted, matched or inspected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistance_decisions', function (Blueprint $table) {
            $table->id();

            // One current decision per report. The unique index is what
            // enforces that, so a double submit cannot leave the office with
            // two contradictory answers on the same case.
            $table->foreignId('damage_report_id')
                ->unique()
                ->constrained()
                ->onDelete('cascade');

            // Denormalised on purpose: every assistance screen groups and
            // filters by farmer, and reading it here saves joining through
            // damage_reports to get back to a column that cannot change
            // anyway (a report never moves to another farmer).
            $table->foreignId('farmer_id')->constrained()->onDelete('restrict');

            $table->enum('decision', ['designated', 'not_designated']);

            // Who decided, and when. Section 91.4: an approval or a refusal
            // is a recorded act, not a silent state change. restrict rather
            // than cascade: deleting the officer must never delete the
            // office's decisions.
            $table->foreignId('decided_by')->constrained('users')->onDelete('restrict');
            $table->timestamp('decided_at');

            // Why. Optional for a designation, which the allocation itself
            // explains, and the place to record "no" honestly: not enough
            // stock, farm outside the covered barangays, already assisted
            // under another programme.
            $table->text('remarks')->nullable();

            $table->timestamps();

            // The assistance screens ask "what has been decided" far more
            // often than they ask about one report, so the decision column
            // is worth its own index.
            $table->index('decision');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistance_decisions');
    }
};
