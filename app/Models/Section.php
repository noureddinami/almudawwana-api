<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'code_id', 'book_id', 'parent_id',
        'number', 'title_ar', 'title_fr', 'level', 'display_order',
    ];

    protected $casts = [
        'level'         => 'integer',
        'display_order' => 'integer',
    ];

    public function code()
    {
        return $this->belongsTo(Code::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function parent()
    {
        return $this->belongsTo(Section::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Section::class, 'parent_id')->orderBy('display_order');
    }

    public function articles()
    {
        return $this->hasMany(Article::class)->orderBy('number_int');
    }
}
