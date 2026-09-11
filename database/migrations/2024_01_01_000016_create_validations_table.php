<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('damage_report_id')->unique()->constrained()->onDelete('cascade');
            $table->foreignId('technician_id')->constrained('users')->onDelete('restrict');

            $table->timestamp('inspection_started_at'); // set when Technician confirms "Start Inspection"

            $table->enum('severity', ['slight', 'moderate', 'partial', 'total'])->nullable();
            $table->decimal('assessed_damage_percent', 5, 2)->nullable(); // technician's own, separate from farmer's
            $table->text('notes')->nullable();

            $table->decimal('latitude', 10, 7)->nullable(); // set ONLY by the Technician, ONLY here
            $table->decimal('longitude', 10, 7)->nullable();

            $table->timestamp('validated_at')->nullable(); // null while inspection is in progress
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validations');
    }
};
