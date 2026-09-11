<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmer_main_crops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->constrained()->onDelete('cascade');
            $table->foreignId('crop_id')->constrained()->onDelete('restrict');
            $table->string('crop_specify')->nullable(); // used only if crop = HVCC
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmer_main_crops');
    }
};
