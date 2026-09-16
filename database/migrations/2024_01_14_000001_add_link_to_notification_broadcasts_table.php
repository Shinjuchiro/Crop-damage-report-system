<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a notification point back at the actual record it is about, not just
 * at who it was sent to.
 *
 * Before this, a NotificationBroadcast only had target_type/target_id, which
 * identify the RECIPIENT (e.g. "specific_farmer" #14) - there was nothing on
 * the row saying which damage report, membership application, or allocation
 * it was actually about. So a farmer opening a bell notification that says
 * "your report was verified" had no way to be taken to that report; the
 * notification was just text.
 *
 * link_type is a short internal key (see App\Models\NotificationBroadcast::
 * LINK_TYPES), not a Laravel morph class string - this system already reads
 * plainer strings than Eloquent's own polymorphic convention (target_type
 * above is the same style), and a short key is what a route-resolution
 * match() reads best. link_id is deliberately NOT a foreign key: the four
 * tables it can point at (damage_reports, farmers, assistance_allocations,
 * assistance_distributions) have very different lifecycles, and a
 * notification is history - it should survive even if the record it once
 * pointed to is later archived or deleted, rather than being dragged down
 * with it via an FK cascade. A dangling link_id just means the "open record"
 * link quietly stops resolving; the notification text itself is unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_broadcasts', function (Blueprint $table) {
            $table->string('link_type')->nullable()->after('target_id');
            $table->unsignedBigInteger('link_id')->nullable()->after('link_type');

            $table->index(['link_type', 'link_id']);
        });
    }

    public function down(): void
    {
        Schema::table('notification_broadcasts', function (Blueprint $table) {
            $table->dropIndex(['link_type', 'link_id']);
            $table->dropColumn(['link_type', 'link_id']);
        });
    }
};
