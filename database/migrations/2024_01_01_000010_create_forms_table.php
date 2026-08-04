<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `forms` holds identity + metadata. The actual field/schema JSON lives on
     * `form_versions` so we get versioning/rollback almost for free (Part D).
     * `current_version_id` is a denormalised pointer to the published version,
     * kept in sync in a DB transaction whenever a new version is activated —
     * this trades a tiny bit of write complexity for O(1) reads on the
     * (very hot) public fill page.
     */
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Multi-tenant scoping (Part D). Nullable so single-tenant setups
            // still work out of the box; enforced via global scope on Form model.
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // Public URL slug — short, unguessable, rotatable independent of id.
            $table->string('slug', 40)->unique();

            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->enum('source', ['manual', 'ai_generated', 'imported_docx', 'imported_xlsx'])
                ->default('manual');

            $table->foreignId('current_version_id')->nullable();

            $table->boolean('accepts_submissions')->default(true);
            $table->timestamp('closes_at')->nullable();
            $table->unsignedInteger('submission_limit')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Hot paths: public fill lookup by slug, dashboard list by tenant/status.
            $table->index(['tenant_id', 'status']);
            $table->index(['owner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};
