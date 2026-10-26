<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SUPPORTING DOCUMENTS FOR A TENANT'S REGISTRATION (Oct 2026)
 *
 * A tenant has no barangay certificate to show, so the office asked for the
 * papers that describe the arrangement instead: a tenancy agreement, an
 * authorization letter from the land owner, and similar. A farmer may need
 * more than one, so they live in a child table rather than in one more column
 * on farmers (the same reason main crops have their own table).
 *
 * The file itself goes to the private disk and is read only through
 * AttachmentController::supportingDocument(), like every other attachment.
 * This table keeps the path, the name the farmer knew it by, and enough about
 * the file to draw a thumbnail or a PDF icon without opening it.
 *
 * Deleting a farmer removes their rows; the files on disk are left for the
 * same housekeeping as every other upload.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmer_supporting_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->constrained('farmers')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->timestamps();

            $table->index('farmer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmer_supporting_documents');
    }
};
