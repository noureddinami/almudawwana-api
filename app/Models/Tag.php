<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    public $timestamps   = false;

    protected $fillable = ['id', 'name_ar', 'name_fr', 'slug', 'description', 'article_count'];

    protected $casts = ['article_count' => 'integer', 'created_at' => 'datetime'];

    public function articles()
    {
        return $this->belongsToMany(Article::class, 'article_tags');
    }

    public function getRouteKeyName(): string { return 'slug'; }
}
