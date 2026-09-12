<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two additions to the MAO -> Association allocation flow.
 *
 * assistance_allocation_beneficiaries is the MAO-reviewed list of farmers a
 * particular allocation was meant for. It is informational only: it does NOT
 * create a per-farmer hand-out. The association still does that itself,
 * through its own distribute screen, into assistance_distributions - that
 * table and this one stay completely separate on purpose. This just gives
 * the office (and, later, the association) a real record of who MAO had in
 * mind when the pool was sent over, instead of that list only ever existing
 * on screen for a moment while allocating.
 *
 * assistance_allocation_documents holds whatever the officer attaches while
 * allocating - the endorsement memo, a beneficiary list PDF, and so on.
 * Modeled as its own table, not a single path column, because an allocation
 * can reasonably have more than one supporting file.
 *
 * NOTE: both table names are long, so the default Laravel-generated foreign
 * key constraint names (table_column_foreign) blow past MySQL's 64-character
 * identifier limit - e.g. the default name for the beneficiaries table's own
 * allocation FK is 68 characters and errors with "Identifier name ... is too
 * long" (MySQL error 1059). Every foreign key below is given an explicit,
 * short constraint name for that reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistance_allocation_beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistance_allocation_id')
                ->constrained(indexName: 'aab_allocation_id_fk')
                ->onDelete('cascade');
            $table->foreignId('farmer_id')
                ->constrained(indexName: 'aab_farmer_id_fk')
                ->onDelete('restrict');

            // The report that qualified them at the moment MAO allocated this.
            // Kept even if the report later changes status, so the record
            // reflects what was true when the decision was made.
            $table->foreignId('damage_report_id')
                ->constrained(indexName: 'aab_damage_report_id_fk')
                ->onDelete('restrict');

            $table->timestamp('created_at')->nullable();

            // The same farmer is only ever listed once against one allocation.
            $table->unique(['assistance_allocation_id', 'farmer_id'], 'aa_beneficiaries_unique');
        });

        Schema::create('assistance_allocation_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistance_allocation_id')
                ->constrained(indexName: 'aad_allocation_id_fk')
                ->onDelete('cascade');

            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users', indexName: 'aad_uploaded_by_fk')
                ->onDelete('set null');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistance_allocation_documents');
        Schema::dropIfExists('assistance_allocation_beneficiaries');
    }
};
