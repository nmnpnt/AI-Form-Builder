<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The JSON schema is the single source of truth (per the brief). Every
     * save — from the drag/drop canvas, the raw JSON editor, AI generation,
     * or an import — creates a new immutable row here rather than mutating
     * one in place. That gives us:
     *   - versioning + rollback (Part D) for free
     *   - a clean audit trail of "what changed and how" (manual/ai/import)
     *   - the ability to cache-compile the *published* version independently
     *     (Redis-cached compiled schema, Part D) without invalidation races
     */
    public function up(): void
    {
        Schema::create('form_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');

            // The canonical schema. See app/Services/FormSchema/SchemaValidator.php
            // for the shape contract. JSON column (not text) so MySQL can index
            // generated columns from it later if reporting needs grow.
            $table->json('schema');

            // Where this version came from — surfaced in the version history UI.
            $table->enum('created_via', ['manual', 'ai_generate', 'ai_edit', 'import_docx', 'import_xlsx', 'rollback'])
                ->default('manual');
            $table->text('change_summary')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['form_id', 'version_number']);
        });

        // Now that form_versions exists, wire forms.current_version_id for real.
        Schema::table('forms', function (Blueprint $table) {
            $table->foreign('current_version_id')
                ->references('id')->on('form_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('form_versions');
    }
};
