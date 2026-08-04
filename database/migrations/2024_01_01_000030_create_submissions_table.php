<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();

            // Pin the version the submitter actually filled out, so old
            // submissions still render correctly even after the form changes.
            $table->foreignId('form_version_id')->constrained()->restrictOnDelete();

            // Field key => value. Deliberately NOT normalised into a row-per-answer
            // table: form schemas are dynamic and per-answer rows would need a
            // migration-free EAV model anyway. JSON keeps writes atomic and simple;
            // we accept that ad-hoc cross-submission analytics needs JSON_EXTRACT
            // or a projection table (see AnalyticsSnapshot in Part D notes).
            $table->json('payload');

            $table->string('submitter_ip', 45)->nullable();
            $table->string('submitter_email')->nullable()->index();
            $table->string('user_agent')->nullable();

            $table->enum('status', ['completed', 'partial'])->default('completed');

            $table->timestamps();

            // Hot path: paginated list per form, newest first, plus search by email.
            $table->index(['form_id', 'created_at']);
        });

        Schema::create('submission_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('field_key');
            $table->string('original_name');
            $table->string('disk_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();

            $table->index(['submission_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_files');
        Schema::dropIfExists('submissions');
    }
};
