<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'code_id', 'number', 'title_ar', 'title_fr', 'display_order',
    ];

    protected $casts = [
        'number'        => 'integer',
        'display_order' => 'integer',
    ];

    public function code()
    {
        return $this->belongsTo(Code::class);
    }

    public function sections()
    {
        return $this->hasMany(Section::class)->orderBy('display_order');
    }
}
