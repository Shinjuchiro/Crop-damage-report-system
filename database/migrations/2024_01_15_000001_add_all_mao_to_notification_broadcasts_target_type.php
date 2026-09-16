<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fixes a real bug found while wiring up the Sept 2026 notification-system
 * rule, not something new that rule needs.
 *
 * App\Models\NotificationBroadcast::AUDIENCES has listed 'all_mao' => 'MAO
 * Staff' for a while, and at least one existing call site already used it
 * (Auth\RegisterController::notifyMaoOfNewRegistration(), which every new
 * farmer registration has been hitting since it was written) - but the
 * notification_broadcasts.target_type column itself is a MySQL ENUM, and
 * 'all_mao' was never added to it (compare the enum list here with the one
 * 2024_01_02_000006 added 'affected_farmers' to - 'all_mao' is missing from
 * both). Under strict SQL mode that INSERT fails outright; the try/catch
 * every dispatch call is wrapped in (see PasswordResetController's use of
 * the same pattern) means it has been failing silently and just logging a
 * warning instead of surfacing anywhere - so MAO has likely never actually
 * received an in-app notification for a new registration, or for any of the
 * new 'all_mao' notifications this build adds (new damage reports, verified
 * reports, non-receipt reports).
 */
return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        DB::statement("
            UPDATE notification_broadcasts
            SET target_type = 'all_farmers'
            WHERE target_type = 'all_mao'
        ");

        DB::statement("
            ALTER TABLE notification_broadcasts
            MODIFY target_type ENUM(
                'all_farmers', 'affected_farmers', 'specific_farmer',
                'all_associations', 'specific_association',
                'all_technicians', 'specific_technician', 'specific_role'
            ) NOT NULL
        ");
    }
};
