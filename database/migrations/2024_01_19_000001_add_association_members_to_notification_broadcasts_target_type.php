<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lets an association announce something to its own members.
 *
 * An officer can now write an announcement - a meeting, a reminder, a
 * deadline - and have it reach the farmers of that association and nobody
 * else. The whole feature needs exactly one new thing in the database: a
 * value in this enum.
 *
 * WHY NOTHING ELSE CHANGES
 *
 * notification_broadcasts already carries everything an announcement is:
 * title, message, category, priority, who wrote it, when it was sent, and
 * a target_id. The per-recipient rows, the read tracking, the SMS status
 * and the bell that shows them are all already there and already work.
 * What was missing was a way to say "the members of association N", as
 * distinct from 'specific_association', which means that association's
 * OFFICER accounts and is what the office uses to write TO an association.
 *
 * So this adds 'association_members', and target_id holds the association
 * id exactly as it does for 'specific_association'. No new table, no new
 * column, and every existing row keeps its meaning.
 *
 * It also doubles as the ownership mark: an announcement belongs to the
 * association whose members it is addressed to, which is how the officer's
 * own list, and the office's exclusion of these from its alerts, are both
 * scoped. See NotificationBroadcast::scopeOfficeAlerts().
 *
 * The enum list below is 2024_01_15_000001's with one value appended;
 * MySQL has no "add a value" statement, so the whole list is restated.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE notification_broadcasts
            MODIFY target_type ENUM(
                'all_farmers', 'affected_farmers', 'specific_farmer',
                'all_associations', 'specific_association', 'association_members',
                'all_technicians', 'specific_technician', 'specific_role',
                'all_mao'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        /* Rolling back would leave announcements pointing at a value the
           column no longer accepts, so they are archived first rather than
           silently rewritten into somebody else's audience: the record of
           what was announced survives, and nothing is re-delivered. */
        DB::statement("
            UPDATE notification_broadcasts
            SET status = 'archived', target_type = 'specific_association'
            WHERE target_type = 'association_members'
        ");

        DB::statement("
            ALTER TABLE notification_broadcasts
            MODIFY target_type ENUM(
                'all_farmers', 'affected_farmers', 'specific_farmer',
                'all_associations', 'specific_association',
                'all_technicians', 'specific_technician', 'specific_role',
                'all_mao'
            ) NOT NULL
        ");
    }
};
