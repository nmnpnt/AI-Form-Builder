<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FormImport extends Model
{
    protected $fillable = [
        'form_id', 'uploaded_by', 'original_filename', 'disk_path', 'file_type',
        'status', 'draft_schema', 'unparsed_blocks', 'error',
    ];

    protected $casts = [
        'draft_schema' => 'array',
        'unparsed_blocks' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(fn (FormImport $i) => $i->uuid ??= (string) Str::uuid());
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}
