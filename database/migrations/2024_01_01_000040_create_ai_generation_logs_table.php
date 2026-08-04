<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The brief explicitly asks us to "log model, tokens and latency" for
     * every generation call. This table also backs the queued-job status
     * polling UI (pending/processing/done/failed) for both create-from-prompt
     * and edit-existing-form flows.
     */
    public function up(): void
    {
        Schema::create('ai_generation_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // returned to the client to poll status
            $table->foreignId('form_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('type', ['generate', 'edit']);
            $table->text('prompt');
            $table->json('input_schema')->nullable(); // for `edit`: the schema before the change

            $table->enum('status', ['pending', 'processing', 'succeeded', 'failed'])->default('pending');
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->text('error')->nullable();

            $table->string('model')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();

            $table->json('output_schema')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generation_logs');
    }
};
