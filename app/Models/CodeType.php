<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodeType extends Model
{
    protected $fillable = ['slug', 'name_ar', 'name_fr', 'color', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function codes()
    {
        return $this->hasMany(Code::class, 'type', 'slug');
    }
}
