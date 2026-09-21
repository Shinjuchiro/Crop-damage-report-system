<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an allocation stand on its own without pointing at one specific
 * catalogue row.
 *
 * Before this, MAO's "Allocate Assistance" modal had a "+ Create a new
 * assistance item" option that defined a brand-new catalogue entry inline
 * (see the AssistanceAllocationController::store() this replaces) - every
 * allocation, no exceptions, had to end up linked to an assistances row.
 * That was removed: officers now pick Cash or In-Kind first, and for
 * In-Kind either an existing catalogue item or "Other" with a typed
 * description. Cash and "Other" have nothing sensible to link to in the
 * catalogue, so assistance_id can no longer be required.
 *
 * Two things change:
 *
 *   1. A new `type` column (cash/in_kind) records what kind of assistance
 *      this allocation is directly on the row itself, rather than only
 *      through the (now optional) assistance_id link. Every display and
 *      report that used to read $allocation->assistance->type would
 *      otherwise go blank the moment assistance_id is null.
 *   2. assistance_id becomes nullable. MySQL requires the existing foreign
 *      key to be dropped before the column can be widened, so it is
 *      dropped and re-added around the MODIFY.
 *
 * Every allocation made up to now does have a catalogue link, so `type` is
 * backfilled from it - nothing existing loses its cash/in-kind label.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistance_allocations', function (Blueprint $table) {
            $table->enum('type', ['cash', 'in_kind'])->nullable()->after('assistance_id');
        });

        DB::statement('
            UPDATE assistance_allocations aa
            INNER JOIN assistances a ON a.id = aa.assistance_id
            SET aa.type = a.type
        ');

        DB::statement('ALTER TABLE assistance_allocations DROP FOREIGN KEY assistance_allocations_assistance_id_foreign');
        DB::statement('ALTER TABLE assistance_allocations MODIFY assistance_id BIGINT UNSIGNED NULL');
        DB::statement('
            ALTER TABLE assistance_allocations
            ADD CONSTRAINT assistance_allocations_assistance_id_foreign
            FOREIGN KEY (assistance_id) REFERENCES assistances (id) ON DELETE RESTRICT
        ');
    }

    public function down(): void
    {
        // Only safe if nothing has actually used the new optional-link
        // allocations yet - a genuine Cash/Other row has no catalogue item
        // to fall back to and would block the NOT NULL below. That is the
        // expected way for this rollback to fail if it is ever attempted
        // after the feature has real data behind it.
        DB::statement('ALTER TABLE assistance_allocations DROP FOREIGN KEY assistance_allocations_assistance_id_foreign');
        DB::statement('ALTER TABLE assistance_allocations MODIFY assistance_id BIGINT UNSIGNED NOT NULL');
        DB::statement('
            ALTER TABLE assistance_allocations
            ADD CONSTRAINT assistance_allocations_assistance_id_foreign
            FOREIGN KEY (assistance_id) REFERENCES assistances (id) ON DELETE RESTRICT
        ');

        Schema::table('assistance_allocations', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
