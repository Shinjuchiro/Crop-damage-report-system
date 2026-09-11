<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->foreignId('association_id')->constrained()->onDelete('restrict');
            $table->foreignId('barangay_id')->constrained()->onDelete('restrict');

            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->date('date_of_birth');
            $table->enum('sex', ['male', 'female']);

            $table->enum('ownership_type', ['land_owner', 'tenant']);
            // Tenant-only fields (required at the application layer when ownership_type = tenant)
            $table->string('landowner_name')->nullable();
            $table->string('landowner_contact', 20)->nullable();
            $table->string('landowner_location')->nullable();
            // Land Owner-only field (required at the application layer when ownership_type = land_owner)
            $table->string('land_ownership_document_path')->nullable();

            $table->text('address'); // free text, used by Technician to locate the farmer
            $table->decimal('farm_size_hectares', 8, 2)->nullable();

            // 6-month inactivity tracking
            $table->enum('activity_status', ['active', 'inactive'])->default('active');
            $table->date('last_activity_date');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmers');
    }
};
