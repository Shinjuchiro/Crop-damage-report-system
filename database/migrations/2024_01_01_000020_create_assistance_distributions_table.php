<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistance_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistance_allocation_id')->constrained()->onDelete('restrict');
            $table->foreignId('farmer_id')->constrained()->onDelete('restrict');
            $table->foreignId('damage_report_id')->constrained()->onDelete('restrict'); // WHICH report made them eligible

            $table->string('in_kind_description')->nullable();
            $table->decimal('quantity', 12, 2)->nullable();

            $table->foreignId('distributed_by')->constrained('users')->onDelete('restrict'); // Association officer
            $table->timestamp('distributed_at');
            $table->text('remarks')->nullable();
            $table->string('distribution_evidence_path')->nullable(); // Association's own proof, separate from farmer's

            // Two SEPARATE status tracks — distribution being done does NOT mean farmer received it
            $table->enum('distribution_status', ['pending_distribution', 'distributed', 'completed', 'cancelled'])
                ->default('pending_distribution');
            $table->enum('receipt_status', ['pending_confirmation', 'confirmed_received', 'not_received'])
                ->default('pending_confirmation');

            $table->text('receipt_note')->nullable(); // farmer's reason/remarks, either outcome
            $table->timestamp('receipt_confirmed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistance_distributions');
    }
};
