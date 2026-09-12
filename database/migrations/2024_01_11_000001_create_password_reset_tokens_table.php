<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's standard password-broker table (config/auth.php ->
 * passwords.users.table). This table was never created for this project -
 * the default framework migration that normally ships with it was removed
 * along with the rest of the stock `users` migration when the custom one
 * (2024_01_01_000001) was written. Nothing else changes: the `User` model
 * already gets `CanResetPassword` for free from `Illuminate\Foundation\Auth\User`,
 * and config/auth.php's `passwords` broker was already pointed at this exact
 * table name. See App\Http\Controllers\Auth\PasswordResetController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
