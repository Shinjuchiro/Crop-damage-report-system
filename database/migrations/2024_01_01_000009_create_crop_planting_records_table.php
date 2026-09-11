<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crop_planting_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->constrained()->onDelete('cascade');
            $table->date('date_submitted');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crop_planting_records');
    }
};
