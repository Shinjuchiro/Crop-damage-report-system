<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two additions the alerts screen needs.
 *
 * 'scheduled_for' lets the MAO write an advisory now and have it go out later;
 * the status enum already had a 'scheduled' value with nowhere to store the time.
 *
 * 'affected_farmers' is added to target_type so an advisory can be sent only to
 * farmers who actually filed a damage report, which is the most common case for
 * validation schedules and assistance notices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_broadcasts', function (Blueprint $table) {
            $table->timestamp('scheduled_for')->nullable()->after('status');
        });

        DB::statement("
            ALTER TABLE notification_broadcasts
            MODIFY target_type ENUM(
                'all_farmers', 'affected_farmers', 'specific_farmer',
                'all_associations', 'specific_association',
                'all_technicians', 'specific_technician', 'specific_role'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE notification_broadcasts
            SET target_type = 'all_farmers'
            WHERE target_type = 'affected_farmers'
        ");

        DB::statement("
            ALTER TABLE notification_broadcasts
            MODIFY target_type ENUM(
                'all_farmers', 'specific_farmer',
                'all_associations', 'specific_association',
                'all_technicians', 'specific_technician', 'specific_role'
            ) NOT NULL
        ");

        Schema::table('notification_broadcasts', function (Blueprint $table) {
            $table->dropColumn('scheduled_for');
        });
    }
};
