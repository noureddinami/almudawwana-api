<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PdfDocument extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'code_id', 'uploaded_by', 'title_ar', 'title_fr',
        'original_filename', 'stored_filename', 'disk', 'file_size',
        'status', 'articles_extracted', 'extraction_log',
        'source_url', 'document_type', 'is_public',
    ];

    protected $casts = [
        'file_size'          => 'integer',
        'articles_extracted' => 'integer',
        'is_public'          => 'boolean',
    ];

    // ── Relations ────────────────────────────────────────

    public function code()
    {
        return $this->belongsTo(Code::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ── Helpers ──────────────────────────────────────────

    public function getDownloadUrl(): string
    {
        return Storage::disk($this->disk)->url('pdfs/' . $this->stored_filename);
    }

    public function getFullPath(): string
    {
        return Storage::disk($this->disk)->path('pdfs/' . $this->stored_filename);
    }

    public function fileSizeForHumans(): string
    {
        $bytes = $this->file_size;
        if ($bytes < 1024)       return $bytes . ' B';
        if ($bytes < 1048576)    return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeImported($query)
    {
        return $query->where('status', 'imported');
    }
}
