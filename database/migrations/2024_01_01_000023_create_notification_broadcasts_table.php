<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->enum('category', [
                'system', 'assistance', 'disaster_alert', 'agricultural_alert', 'announcement', 'event',
            ]);
            $table->enum('priority', ['normal', 'important', 'urgent', 'critical']);

            $table->enum('target_type', [
                'all_farmers', 'specific_farmer', 'all_associations', 'specific_association',
                'all_technicians', 'specific_technician', 'specific_role',
            ]);
            $table->unsignedBigInteger('target_id')->nullable();

            $table->string('attachment_path')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'sent', 'failed', 'archived'])->default('draft');

            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_broadcasts');
    }
};
