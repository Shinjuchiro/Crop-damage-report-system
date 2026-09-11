<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistance_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistance_id')->constrained()->onDelete('restrict');
            $table->foreignId('association_id')->constrained()->onDelete('restrict'); // MAO -> Association ONLY, no bypass
            $table->foreignId('disaster_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('crop_id')->nullable()->constrained()->onDelete('set null');

            // In-kind only fields — left null for cash assistance (no cash amount tracked, per scope)
            $table->string('in_kind_description')->nullable();
            $table->decimal('allocated_quantity', 12, 2)->nullable();
            $table->decimal('distributed_quantity', 12, 2)->nullable(); // may legitimately differ from allocated

            $table->foreignId('allocated_by')->constrained('users')->onDelete('restrict');
            $table->timestamp('allocated_at');

            $table->timestamp('distributed_to_association_at')->nullable();
            $table->foreignId('distributed_by')->nullable()->constrained('users')->onDelete('set null');

            $table->text('remarks')->nullable();
            $table->string('supporting_evidence_path')->nullable();

            $table->enum('status', ['pending', 'allocated', 'distributed', 'completed', 'cancelled'])->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistance_allocations');
    }
};
