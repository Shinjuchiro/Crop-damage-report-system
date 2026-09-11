<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crop_planting_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crop_planting_record_id')->constrained()->onDelete('cascade');
            $table->string('file_path');
            $table->timestamp('uploaded_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crop_planting_photos');
    }
};
