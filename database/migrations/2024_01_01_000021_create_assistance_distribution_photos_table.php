<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistance_distribution_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assistance_distribution_id');
            $table->foreign('assistance_distribution_id', 'adp_distribution_id_foreign')
                ->references('id')->on('assistance_distributions')
                ->onDelete('cascade');
            $table->enum('photo_type', ['receipt', 'non_receipt']);
            $table->string('file_path');
            $table->timestamp('uploaded_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistance_distribution_photos');
    }
};