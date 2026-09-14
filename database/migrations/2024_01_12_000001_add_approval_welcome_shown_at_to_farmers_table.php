<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks whether a farmer has already been shown the one-time
 * "Registration Approved" welcome dialog on their dashboard, the first
 * time they log in after MAO approves them. Null means "not shown yet";
 * once shown, it is stamped with when, and never shown again.
 *
 * See App\Http\Controllers\Farmer\DashboardController and
 * resources/views/farmer/dashboard.blade.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->timestamp('approval_welcome_shown_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->dropColumn('approval_welcome_shown_at');
        });
    }
};
