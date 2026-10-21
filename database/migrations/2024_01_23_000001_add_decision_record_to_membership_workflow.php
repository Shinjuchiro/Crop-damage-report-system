<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WHAT THE MEMBERSHIP WORKFLOW STILL COULD NOT RECORD (revision, Oct 2026)
 *
 * Migration 2024_01_21_000001 built the association-first workflow and the
 * columns that say where an application has got to and who moved it. This
 * one adds the things the workflow was doing but not keeping:
 *
 *   1. The association's verification remarks. An officer forwards an
 *      application to say "this person is one of ours", and the office is
 *      supposed to see what the officer said about it. There was nowhere to
 *      put it, so forwardToMao() took no note and the office saw only that
 *      somebody had forwarded it.
 *
 *   2. The office's decision and its reason. Approving set users.status to
 *      'active' and that was the whole record: no note, and no row saying
 *      WHICH officer decided or when. The rejection reason was worse - it
 *      was optional, and when it was given it went into the audit log's
 *      action text and into the notification body, so the application
 *      itself never held the reason it was refused.
 *
 *   3. An audit log that can hold a sentence.
 *
 * THE audit_logs.action COLUMN WAS A LATENT CRASH
 *
 * `action` is a string, so VARCHAR(255). The membership controllers append
 * the correction note to it:
 *
 *     'Returned membership application for correction: ' . $name . ' - ' . $note
 *
 * and the note validates at max:1000. That prefix plus a name is around 70
 * characters, so any correction note longer than roughly 180 characters
 * overflows the column. MySQL in strict mode (Laravel's default) raises
 * "Data too long for column 'action'" rather than truncating, and the write
 * is inside the transaction that returns the application - so an officer
 * writing a careful, detailed explanation got a 500 error and the
 * application did not move, while an officer writing "wrong barangay"
 * succeeded. The more useful the note, the more likely the failure.
 *
 * Nobody had hit it yet because the only notes written so far were short.
 * The revision this migration belongs to requires remarks on return and on
 * rejection, which makes long notes the normal case rather than the unlucky
 * one.
 *
 * So `action` becomes TEXT, and the remarks move into a column of their own
 * where they belong. Widening a column never loses data; every existing row
 * is untouched and still readable.
 *
 * WHY from_status / to_status / remarks GO ON audit_logs
 *
 * The revision asks for a history recording, per event: the action, the
 * previous status, the new status, who did it, the remarks, and when. It
 * also says plainly not to build a second history system if one already
 * exists. audit_logs is that system - every transition already writes to it.
 * What it could not do was answer "what status did this move FROM", because
 * that only existed inside a human-readable sentence.
 *
 * Three nullable columns, so every existing audit row stays valid and simply
 * has no transition recorded, which is true of it.
 *
 * NOTHING IS BACKFILLED
 *
 * Deliberately, and unlike 2024_01_21_000001, which did backfill. There the
 * value was knowable: every existing farmer was with the office, because
 * there was nowhere else to be. Here it is not. Guessing that a farmer who
 * is active today was decided by, say, the first MAO account, at the time
 * their account row was last touched, would put a fact in the audit trail
 * that nobody checked. An audit trail that invents entries is worse than one
 * with gaps, so decided_by stays null for everyone decided before today and
 * the interface reads that as "decided before this was recorded".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            /* What the association said when it vouched for this member.
               Optional: an officer who has nothing to add should not be made
               to type something, and a forced note is a note that says
               "ok". The office sees "no remarks given" rather than a box
               full of filler. */
            $table->text('forward_note')->nullable()->after('forwarded_at');

            /* The office's own words on the decision. Required when
               rejecting (the revision asks for a reason, and a farmer told
               only "not approved" has nothing to act on), optional when
               approving. */
            $table->text('decision_note')->nullable()->after('resubmission_count');

            $table->foreignId('decided_by')->nullable()->after('decision_note')
                ->constrained('users')->nullOnDelete();

            $table->timestamp('decided_at')->nullable()->after('decided_by');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            // See the note above: this column was one long correction note
            // away from a 500 error.
            $table->text('action')->change();

            $table->string('from_status', 50)->nullable()->after('action');
            $table->string('to_status', 50)->nullable()->after('from_status');
            $table->text('remarks')->nullable()->after('to_status');

            /* The history panel asks "every audit row for farmer 42", which
               without this is a full scan of a table that only ever grows.
               It is cheap to add now and awkward to add once the office has
               a year of rows. */
            $table->index(['target_table', 'target_id'], 'audit_logs_target_index');
        });
    }

    public function down(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decided_by');
            $table->dropColumn(['forward_note', 'decision_note', 'decided_at']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_target_index');
            $table->dropColumn(['from_status', 'to_status', 'remarks']);

            /* Back to VARCHAR(255). This is the one part of this rollback
               that can lose data, because any action text longer than 255
               characters cannot fit, so it is truncated rather than the
               migration failing halfway. Rolling back is a developer action
               on a developer's machine; it is called out here so it is not a
               surprise. */
            $table->string('action', 255)->change();
        });
    }
};
