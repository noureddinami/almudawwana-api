<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Commentary;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminCommentaryController extends Controller
{
    // ── User comments moderation ──────────────────────────────────────────────

    public function index(Request $request)
    {
        $comments = Commentary::where('type', 'commentary')
            ->with([
                'author:id,full_name,email',
                'article:id,number,slug,code_id',
                'article.code:id,slug,title_ar',
            ])
            ->when($request->input('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->input('q'), fn($q, $s) => $q->where('content_ar', 'like', "%$s%"))
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json($comments);
    }

    public function update(Request $request, Commentary $commentary)
    {
        $data = $request->validate([
            'status'           => 'sometimes|in:approved,rejected,pending',
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        if (isset($data['status'])) {
            $data['reviewed_by'] = $request->user()->id;
            $data['reviewed_at'] = now();
        }

        $commentary->update($data);

        // Update article comment_count when a comment is approved/rejected
        $count = Commentary::where('article_id', $commentary->article_id)
                           ->where('type', 'commentary')
                           ->where('status', 'approved')
                           ->count();
        $commentary->article()->update(['comment_count' => $count]);

        return response()->json(['message' => 'تم تحديث التعليق', 'comment' => $commentary->fresh()]);
    }

    public function destroy(Commentary $commentary)
    {
        $articleId = $commentary->article_id;
        $commentary->delete();

        $count = Commentary::where('article_id', $articleId)
                           ->where('type', 'commentary')
                           ->where('status', 'approved')
                           ->count();
        Article::where('id', $articleId)->update(['comment_count' => $count]);

        return response()->json(['message' => 'تم حذف التعليق']);
    }

    // ── Admin notes (annotations) ─────────────────────────────────────────────

    public function articleNotes(Article $article)
    {
        $notes = Commentary::where('article_id', $article->id)
            ->where('type', 'annotation')
            ->with(['author:id,full_name'])
            ->orderByDesc('created_at')
            ->get(['id', 'article_id', 'author_id', 'content_ar', 'created_at']);

        return response()->json($notes);
    }

    public function storeNote(Request $request, Article $article)
    {
        $data = $request->validate([
            'content_ar' => 'required|string|min:2|max:5000',
        ]);

        $note = Commentary::create([
            'id'         => Str::uuid(),
            'article_id' => $article->id,
            'author_id'  => $request->user()->id,
            'content_ar' => $data['content_ar'],
            'type'       => 'annotation',
            'status'     => 'approved',
        ]);

        $note->load('author:id,full_name');

        return response()->json(['message' => 'تم إضافة الملاحظة', 'note' => $note], 201);
    }

    public function destroyNote(Commentary $commentary)
    {
        $commentary->delete();
        return response()->json(['message' => 'تم حذف الملاحظة']);
    }
}
