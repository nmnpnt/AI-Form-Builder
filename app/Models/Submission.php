<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Submission extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id', 'form_version_id', 'payload', 'submitter_ip',
        'submitter_email', 'user_agent', 'status',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(fn (Submission $s) => $s->uuid ??= (string) Str::uuid());
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function formVersion()
    {
        return $this->belongsTo(FormVersion::class);
    }

    public function files()
    {
        return $this->hasMany(SubmissionFile::class);
    }

    /** Human-readable value lookup used by the submissions table & CSV export. */
    public function value(string $fieldKey): mixed
    {
        return $this->payload[$fieldKey] ?? null;
    }
}
