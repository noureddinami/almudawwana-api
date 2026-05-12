<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commentary extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'article_id', 'author_id', 'title_ar', 'content_ar',
        'type', 'status', 'reviewed_by', 'reviewed_at',
        'rejection_reason', 'upvotes', 'downvotes',
    ];

    protected $casts = [
        'upvotes'     => 'integer',
        'downvotes'   => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
