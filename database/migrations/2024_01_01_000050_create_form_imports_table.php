<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_imports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('form_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('original_filename');
            $table->string('disk_path');
            $table->enum('file_type', ['docx', 'xlsx']);

            $table->enum('status', ['queued', 'processing', 'needs_review', 'committed', 'failed'])
                ->default('queued');

            // Deterministically-parsed draft schema, shown on the preview/mapping
            // screen for the user to correct before it becomes a real form_version.
            $table->json('draft_schema')->nullable();

            // Blocks the parser couldn't confidently map — surfaced to the user
            // instead of silently dropped, per the brief's "report unparseable
            // blocks clearly" requirement.
            $table->json('unparsed_blocks')->nullable();

            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_imports');
    }
};
