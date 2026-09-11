<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistances', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "Rice Seeds", "Agricultural Financial Assistance"
            $table->enum('type', ['cash', 'in_kind']);
            $table->text('description')->nullable();
            $table->foreignId('disaster_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('crop_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('available_quantity_or_amount', 12, 2)->nullable(); // null/NA for cash type
            $table->enum('status', ['active', 'inactive', 'closed'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistances');
    }
};
