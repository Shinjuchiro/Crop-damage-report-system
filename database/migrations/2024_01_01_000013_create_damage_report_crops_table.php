<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_report_crops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('damage_report_id')->constrained()->onDelete('cascade');
            $table->foreignId('crop_id')->constrained()->onDelete('restrict');
            $table->string('crop_specify')->nullable();

            $table->decimal('damaged_area_hectares', 8, 2);
            $table->date('date_planted');
            $table->decimal('estimated_damage_percent', 5, 2); // farmer's own estimate - never overwritten
            $table->decimal('production_cost', 12, 2)->nullable();
            $table->decimal('total_damage_cost', 12, 2)->nullable(); // per-crop, so monthly reports can break it down by crop
            $table->decimal('farmgate_price_per_kg', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_report_crops');
    }
};
