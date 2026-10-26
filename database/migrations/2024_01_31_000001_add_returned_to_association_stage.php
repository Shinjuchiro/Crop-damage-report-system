<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * THE OFFICE RETURNS AN APPLICATION TO THE ASSOCIATION (Oct 2026)
 *
 * Until now, when the Municipal Agriculture Office returned an application
 * for correction it went straight to the farmer. The office asked for the
 * association to stay in the loop instead:
 *
 *   Forwarded to MAO
 *     -> Returned to Association   (the office wrote what is wrong)
 *     -> Returned for Correction   (the association passed it to the farmer)
 *     -> Submitted to Association  (the farmer corrected it, as before)
 *
 * So the farmer only ever hears from their own association, and the
 * association sees the office's remarks before anything reaches the farmer.
 *
 * WHAT THIS ADDS
 *
 * - one more value in farmers.membership_stage
 * - the office's return remarks, who wrote them and when, in their own
 *   columns. They are kept apart from return_note on purpose: return_note is
 *   what the FARMER reads, and the association may reword it. The office's
 *   original wording must survive that.
 *
 * Applications already returned to the farmer by the office before this
 * migration are left exactly as they are.
 */
return new class extends Migration
{
    private const STAGES = [
        'submitted_to_association',
        'under_association_review',
        'forwarded_to_mao',
        'returned_for_correction',
        'returned_to_association',
    ];

    public function up(): void
    {
        $this->setStages(self::STAGES);

        Schema::table('farmers', function (Blueprint $table) {
            $table->foreignId('mao_returned_by')->nullable()
                ->after('resubmission_count')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('mao_returned_at')->nullable()->after('mao_returned_by');
            $table->text('mao_return_note')->nullable()->after('mao_returned_at');
        });
    }

    public function down(): void
    {
        // An application sitting in the new stage goes back to the office's
        // queue, where it was before the office returned it.
        DB::table('farmers')
            ->where('membership_stage', 'returned_to_association')
            ->update(['membership_stage' => 'forwarded_to_mao']);

        Schema::table('farmers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mao_returned_by');
            $table->dropColumn(['mao_returned_at', 'mao_return_note']);
        });

        $this->setStages(array_slice(self::STAGES, 0, 4));
    }

    /**
     * MODIFY keeps the column where it is, so nothing after it moves.
     * Only MySQL and MariaDB have a real ENUM to change; any other driver
     * stores the stage as plain text and needs nothing.
     */
    private function setStages(array $stages): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $list = "'" . implode("','", $stages) . "'";

        DB::statement("ALTER TABLE farmers MODIFY membership_stage ENUM({$list}) NOT NULL DEFAULT 'submitted_to_association'");
    }
};
