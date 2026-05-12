<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Code extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'slug', 'title_ar', 'title_fr', 'description_ar',
        'type', 'status', 'official_number', 'bo_number', 'bo_date',
        'promulgation_date', 'effective_date', 'total_articles',
        'pdf_official_url', 'source_url',
    ];

    protected $casts = [
        'bo_date'           => 'date',
        'promulgation_date' => 'date',
        'effective_date'    => 'date',
        'total_articles'    => 'integer',
    ];

    // ── Relations ────────────────────────────────────────

    public function books()
    {
        return $this->hasMany(Book::class)->orderBy('display_order');
    }

    public function sections()
    {
        return $this->hasMany(Section::class)->orderBy('display_order');
    }

    public function articles()
    {
        return $this->hasMany(Article::class);
    }

    // ── Scopes ───────────────────────────────────────────

    public function scopeInForce($query)
    {
        return $query->where('status', 'in_force');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ── Route model binding ──────────────────────────────

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
