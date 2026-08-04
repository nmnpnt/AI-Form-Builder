<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AiGenerationLog extends Model
{
    protected $fillable = [
        'form_id', 'requested_by', 'type', 'prompt', 'input_schema',
        'status', 'attempt', 'error', 'model', 'prompt_tokens',
        'completion_tokens', 'latency_ms', 'output_schema',
    ];

    protected $casts = [
        'input_schema' => 'array',
        'output_schema' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(fn (AiGenerationLog $log) => $log->uuid ??= (string) Str::uuid());
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}
