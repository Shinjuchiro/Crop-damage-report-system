<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AN ALLOCATION IS A BATCH OF FARMERS, NOT A PARCEL FOR ONE ASSOCIATION
 * (Oct 2026, client decision)
 *
 * The original design sent assistance from the office to one association,
 * and the line creating this column said so in its own comment: "MAO ->
 * Association ONLY, no bypass". The office works differently. Assistance
 * comes in from time to time, the office decides which farmers are valid
 * for it, and those farmers can belong to any association or to several.
 *
 * So the office now picks farmers from every verified report in the
 * municipality and association_id stops being the thing an allocation is
 * addressed to.
 *
 * WHY NULLABLE RATHER THAN DROPPED
 *
 * Allocations already recorded were addressed to an association, and that
 * is a true fact about them that nothing should erase. Nullable keeps every
 * one of those rows readable and lets new ones leave it empty. Dropping the
 * column would throw away the only record of who past allocations were sent
 * to, in exchange for a tidier table nobody looks at.
 *
 * The foreign key is kept, so a null is the only way out: an allocation
 * still cannot point at an association that does not exist.
 *
 * WHAT REPLACED IT
 *
 * "Which associations does this allocation concern" is now answered by the
 * designated farmers themselves, through
 * assistance_allocation_beneficiaries -> farmers.association_id. That is
 * also what the association's own Assistance screens read, and what decides
 * which associations get told about a new allocation. One allocation can
 * concern several associations, which the old column could not express at
 * all.
 *
 * The same drop-modify-recreate dance as migration 2024_01_17_000001, and
 * for the same reason: MySQL will not let a column backing a foreign key be
 * modified while the constraint is on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE assistance_allocations DROP FOREIGN KEY assistance_allocations_association_id_foreign');
        DB::statement('ALTER TABLE assistance_allocations MODIFY association_id BIGINT UNSIGNED NULL');
        DB::statement('
            ALTER TABLE assistance_allocations
            ADD CONSTRAINT assistance_allocations_association_id_foreign
            FOREIGN KEY (association_id) REFERENCES associations (id) ON DELETE RESTRICT
        ');
    }

    /**
     * Going back needs every row to have an association again, and a row
     * created after this migration will not. Rather than invent one, this
     * fills a null from the first designated farmer's own association, which
     * is the closest true answer available, and leaves the rest to fail
     * loudly rather than quietly guessing.
     */
    public function down(): void
    {
        DB::statement('
            UPDATE assistance_allocations aa
            SET association_id = (
                SELECT f.association_id
                FROM assistance_allocation_beneficiaries b
                INNER JOIN farmers f ON f.id = b.farmer_id
                WHERE b.assistance_allocation_id = aa.id
                ORDER BY b.id
                LIMIT 1
            )
            WHERE aa.association_id IS NULL
        ');

        DB::statement('ALTER TABLE assistance_allocations DROP FOREIGN KEY assistance_allocations_association_id_foreign');
        DB::statement('ALTER TABLE assistance_allocations MODIFY association_id BIGINT UNSIGNED NOT NULL');
        DB::statement('
            ALTER TABLE assistance_allocations
            ADD CONSTRAINT assistance_allocations_association_id_foreign
            FOREIGN KEY (association_id) REFERENCES associations (id) ON DELETE RESTRICT
        ');
    }
};
