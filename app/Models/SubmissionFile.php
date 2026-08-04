<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SubmissionFile extends Model
{
    protected $fillable = [
        'submission_id', 'field_key', 'original_name', 'disk_path', 'mime_type', 'size_bytes',
    ];

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function url(): string
    {
        return Storage::disk('local')->url($this->disk_path);
    }
}
