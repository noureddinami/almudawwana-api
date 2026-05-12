<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'code_id', 'section_id', 'number', 'number_int', 'slug',
        'content_ar', 'content_fr', 'status', 'current_version',
        'view_count', 'bookmark_count', 'comment_count',
        'last_amended_date', 'abrogated_date', 'source',
    ];

    protected $casts = [
        'number_int'       => 'integer',
        'current_version'  => 'integer',
        'view_count'       => 'integer',
        'bookmark_count'   => 'integer',
        'comment_count'    => 'integer',
        'last_amended_date'=> 'date',
        'abrogated_date'   => 'date',
    ];

    // ── Relations ────────────────────────────────────────

    public function code()
    {
        return $this->belongsTo(Code::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function versions()
    {
        return $this->hasMany(ArticleVersion::class)->orderByDesc('version_number');
    }

    public function commentaries()
    {
        return $this->hasMany(Commentary::class)
                    ->where('status', 'approved')
                    ->orderByDesc('upvotes');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'article_tags');
    }

    public function jurisprudence()
    {
        return $this->belongsToMany(Jurisprudence::class, 'jurisprudence_articles')
                    ->withPivot('relevance_note');
    }

    // ── Scopes ───────────────────────────────────────────

    public function scopeInForce($query)
    {
        return $query->where('status', 'in_force');
    }

    // ── Helpers ──────────────────────────────────────────

    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
