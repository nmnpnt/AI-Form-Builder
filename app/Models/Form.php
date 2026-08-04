<?php

namespace App\Models;

use App\Services\FormSchema\SchemaValidator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Form extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'owner_id', 'title', 'description', 'slug',
        'status', 'source', 'current_version_id',
        'accepts_submissions', 'closes_at', 'submission_limit',
    ];

    protected $casts = [
        'accepts_submissions' => 'boolean',
        'closes_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Form $form) {
            $form->uuid ??= (string) Str::uuid();
            $form->slug ??= Str::lower(Str::random(10));
        });

        // Multi-tenant scoping (Part D): when a tenant context is bound,
        // every query is automatically scoped to it. Single-tenant installs
        // simply never bind `currentTenantId` and this scope is a no-op.
        static::addGlobalScope('tenant', function ($query) {
            if (app()->bound('currentTenantId') && app('currentTenantId')) {
                $query->where('tenant_id', app('currentTenantId'));
            }
        });

        static::saved(fn (Form $form) => Cache::forget("form-schema:{$form->slug}"));
        static::deleted(fn (Form $form) => Cache::forget("form-schema:{$form->slug}"));
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function versions()
    {
        return $this->hasMany(FormVersion::class)->orderByDesc('version_number');
    }

    public function currentVersion()
    {
        return $this->belongsTo(FormVersion::class, 'current_version_id');
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    public function imports()
    {
        return $this->hasMany(FormImport::class);
    }

    /**
     * The published schema, Redis-cached (Part D) since the public fill page
     * is the hottest read path in the app. Cache is invalidated on save/delete
     * above and whenever publishVersion() runs.
     */
    public function compiledSchema(): array
    {
        return Cache::remember(
            "form-schema:{$this->slug}",
            (int) config('formbuilder.schema_cache_ttl', 3600),
            fn () => $this->currentVersion?->schema ?? ['sections' => []]
        );
    }

    /**
     * Persist a new schema as a new version, validate it first, and make it
     * current. This is the single write path used by the manual builder, the
     * raw JSON editor, AI generation/edit, and committed imports — so every
     * caller gets the same guarantees (schema-valid or it doesn't save).
     */
    public function publishVersion(array $schema, string $via = 'manual', ?int $userId = null, ?string $summary = null): FormVersion
    {
        app(SchemaValidator::class)->validateOrFail($schema);

        return DB::transaction(function () use ($schema, $via, $userId, $summary) {
            $next = ($this->versions()->max('version_number') ?? 0) + 1;

            $version = $this->versions()->create([
                'version_number' => $next,
                'schema' => $schema,
                'created_via' => $via,
                'change_summary' => $summary,
                'created_by' => $userId,
            ]);

            $this->forceFill(['current_version_id' => $version->id])->save();

            return $version;
        });
    }

    /** Part D: rollback simply republishes an old version's schema as a new one. */
    public function rollbackTo(FormVersion $target, ?int $userId = null): FormVersion
    {
        return $this->publishVersion(
            $target->schema,
            via: 'rollback',
            userId: $userId,
            summary: "Rolled back to v{$target->version_number}",
        );
    }
}
