<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The SMS side of "Forgot password?" (App\Http\Controllers\Auth\
 * PasswordResetController, App\Models\PasswordResetOtp). Separate from
 * Laravel's own password_reset_tokens table (2024_01_11_000001) because a
 * token there is a long random string meant only to be clicked from an
 * emailed link - it is not something a person could reasonably be asked to
 * type in by hand. This table is the SMS equivalent: a short 6-digit code
 * a farmer reads off a text message and types into the web app.
 *
 * The code itself is never stored - only its hash (otp_hash), the same way
 * users.password never stores a real password. consumed_at stops a code
 * being reused once the reset succeeds, and attempts is what locks a code
 * out after too many wrong guesses (see PasswordResetOtp::isLocked()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('otp_hash');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();

            // Looking up "the current code for this user" is the only query
            // this table ever serves.
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_otps');
    }
};
