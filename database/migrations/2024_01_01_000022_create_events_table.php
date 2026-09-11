<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('event_date');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->string('venue')->nullable();
            $table->string('organizer')->nullable(); // display text, not necessarily a system user

            $table->enum('target_type', [
                'all_farmers', 'specific_farmer', 'all_associations', 'specific_association',
                'all_technicians', 'specific_technician', 'specific_role',
            ]);
            $table->unsignedBigInteger('target_id')->nullable(); // no FK constraint - meaning depends on target_type

            $table->enum('priority', ['normal', 'important', 'urgent', 'critical'])->default('normal');
            $table->string('attachment_path')->nullable();
            $table->enum('status', ['upcoming', 'ongoing', 'completed', 'cancelled'])->default('upcoming');

            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
