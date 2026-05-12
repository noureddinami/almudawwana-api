<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Commentary;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CommentaryController extends Controller
{
    public function index(Article $article)
    {
        $comments = Commentary::where('article_id', $article->id)
            ->where('status', 'approved')
            ->where('type', 'commentary')
            ->with(['author:id,full_name'])
            ->orderByDesc('created_at')
            ->get(['id', 'article_id', 'author_id', 'content_ar', 'upvotes', 'created_at']);

        return response()->json($comments);
    }

    public function store(Request $request, Article $article)
    {
        $data = $request->validate([
            'content_ar' => 'required|string|min:10|max:2000',
        ]);

        Commentary::create([
            'id'         => Str::uuid(),
            'article_id' => $article->id,
            'author_id'  => $request->user()->id,
            'content_ar' => $data['content_ar'],
            'type'       => 'commentary',
            'status'     => 'pending',
        ]);

        return response()->json([
            'message' => 'تم إرسال تعليقك وسيُنشر بعد المراجعة',
        ], 201);
    }
}
