<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crop_planting_record_crops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crop_planting_record_id')->constrained()->onDelete('cascade');
            $table->foreignId('crop_id')->constrained()->onDelete('restrict');
            $table->string('crop_specify')->nullable();
            $table->date('date_planted');
            $table->decimal('area_hectares', 8, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crop_planting_record_crops');
    }
};
