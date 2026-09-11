<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The MAO confirms land ownership through a Barangay Certificate rather than a
 * land title, so the column is renamed to say what it actually holds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->renameColumn('land_ownership_document_path', 'barangay_certificate_path');
        });
    }

    public function down(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->renameColumn('barangay_certificate_path', 'land_ownership_document_path');
        });
    }
};
