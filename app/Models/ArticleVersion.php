<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleVersion extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    public $timestamps   = false;

    protected $fillable = [
        'id', 'article_id', 'version_number', 'content_ar',
        'amendment_law', 'amendment_bo', 'amendment_date',
        'change_summary', 'is_current',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'amendment_date' => 'date',
        'is_current'     => 'boolean',
        'created_at'     => 'datetime',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
