<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which Crop Management records each person has already opened, so the
 * sidebar and tab badges can count what is NEW to that person and go down
 * as they open things.
 *
 * badges_since stops every record that already exists from showing up as
 * "new" the first time this runs: only records filed after it count.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // planting | damage_report | validation
            $table->string('record_type', 30);
            $table->unsignedBigInteger('record_id');
            $table->timestamp('seen_at')->useCurrent();

            $table->unique(['user_id', 'record_type', 'record_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('badges_since')->nullable();
        });

        DB::table('users')->update(['badges_since' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('badges_since');
        });

        Schema::dropIfExists('record_views');
    }
};
