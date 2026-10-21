<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * THE ASSOCIATION-FIRST MEMBERSHIP WORKFLOW (revision step 6)
 *
 * Until now a farmer registered and the application went straight into the
 * MAO's queue. The office asked for the farmers' association to read it
 * first, because the association is the body that actually knows whether the
 * person is one of its members, and the office does not.
 *
 * So an application now travels:
 *
 *   Submitted to Association
 *     -> Under Association Review     (an officer opened it)
 *     -> Forwarded to MAO             (the officer vouched for the member)
 *     -> Approved / Rejected          (the office decides, as before)
 *
 * and at either review stage it can go back:
 *
 *     -> Returned for Correction      (the farmer has to fix something)
 *     -> Submitted to Association     (every resubmission restarts the loop)
 *
 * WHY A COLUMN HERE AND NOT A WIDER users.status
 *
 * users.status answers "may this account use the system", which is a
 * different question from "where has this application got to". Two of the
 * new stages (Under Association Review, Forwarded to MAO) say nothing at all
 * about access: in all four the farmer is pending and cannot log in to the
 * farmer area. Widening users.status would have put application routing
 * inside the column the login middleware reads, which is the last column
 * that should grow new values.
 *
 * The outcome is deliberately NOT in this enum either. Approved and Rejected
 * already exist, as users.status 'active' and 'rejected', and storing them
 * twice would mean every approval writing two columns and the first write
 * path that forgot leaving a farmer who can log in while their application
 * still reads "Forwarded to MAO". App\Support\MembershipApplication resolves
 * the one status to display, reading the account's own status first; it is
 * the same precedence pattern as App\Support\AssistanceCase, so the system
 * has one way of answering "which status is this record in" rather than two.
 *
 * WHAT THE AUDIT COLUMNS ARE FOR
 *
 * Each handover records who did it and when, the way the technician's
 * inspection already records who started it. The current state lives here;
 * the history of a loop that went round more than once is in audit_logs,
 * which this workflow writes to on every transition (section 81).
 *
 * EXISTING ROWS
 *
 * Every farmer already in the database is set to 'forwarded_to_mao' below,
 * not to the default. The office is already reviewing those applications and
 * nobody should have to route them back through an association to finish a
 * job that is halfway done. The audit columns stay null for them on purpose:
 * null here means "this application predates the workflow", and inventing a
 * forwarded_at date for it would be putting a fact in the database that
 * nobody recorded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->enum('membership_stage', [
                'submitted_to_association',
                'under_association_review',
                'forwarded_to_mao',
                'returned_for_correction',
            ])->default('submitted_to_association')->after('association_id');

            // Who opened the review, and when. Mirrors the technician's
            // Start Inspection: the stage change is the record that somebody
            // took responsibility for looking at it.
            $table->foreignId('association_reviewed_by')->nullable()
                ->after('membership_stage')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('association_review_started_at')->nullable()
                ->after('association_reviewed_by');

            $table->foreignId('forwarded_by')->nullable()
                ->after('association_review_started_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('forwarded_at')->nullable()->after('forwarded_by');

            /* The return, and the only one of these the farmer ever reads.
               returned_by_role is stored rather than worked out from the
               user's role, because a role can change later and this has to
               keep saying who sent it back at the time. return_note is
               required by the application layer: a return with no reason is
               a farmer told to fix something without being told what. */
            $table->foreignId('returned_by')->nullable()
                ->after('forwarded_at')
                ->constrained('users')->nullOnDelete();
            $table->enum('returned_by_role', ['association', 'mao'])->nullable()
                ->after('returned_by');
            $table->text('return_note')->nullable()->after('returned_by_role');
            $table->timestamp('returned_at')->nullable()->after('return_note');

            // How many times this application has come back round. Shown to
            // the office, because an application on its fourth pass is worth
            // a phone call rather than a fifth note.
            $table->unsignedSmallInteger('resubmission_count')->default(0)
                ->after('returned_at');

            // Both association queues and the MAO queue filter on this.
            $table->index('membership_stage');
        });

        /* Everything that already exists is mid-review at the office. See
           the class docblock: the default above applies only to rows created
           after this migration runs. */
        DB::table('farmers')->update(['membership_stage' => 'forwarded_to_mao']);
    }

    public function down(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('association_reviewed_by');
            $table->dropConstrainedForeignId('forwarded_by');
            $table->dropConstrainedForeignId('returned_by');

            $table->dropIndex(['membership_stage']);

            $table->dropColumn([
                'membership_stage',
                'association_review_started_at',
                'forwarded_at',
                'returned_by_role',
                'return_note',
                'returned_at',
                'resubmission_count',
            ]);
        });
    }
};
