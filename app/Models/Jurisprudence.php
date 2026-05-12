<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jurisprudence extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'case_number', 'decision_date', 'court_type', 'court_name',
        'title_ar', 'summary_ar', 'full_text_ar', 'pdf_url', 'source',
    ];

    protected $casts = ['decision_date' => 'date'];

    public function articles()
    {
        return $this->belongsToMany(Article::class, 'jurisprudence_articles')
                    ->withPivot('relevance_note');
    }
}
