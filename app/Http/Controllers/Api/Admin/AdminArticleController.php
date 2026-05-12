<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Code;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminArticleController extends Controller
{
    public function index(Request $request)
    {
        $articles = Article::query()
            ->select('id', 'code_id', 'number', 'number_int', 'slug',
                     'status', 'view_count', 'comment_count', 'created_at')
            ->with(['code:id,slug,title_ar'])
            ->when($request->input('q'), fn($q, $search) =>
                $q->where('number', 'like', "%$search%")
                  ->orWhere('content_ar', 'like', "%$search%")
            )
            ->when($request->input('code'), function ($q) use ($request) {
                $code = Code::where('slug', $request->code)->first();
                if ($code) $q->where('code_id', $code->id);
            })
            ->when($request->input('status'), fn($q) => $q->where('status', $request->status))
            ->orderByRaw("
                code_id,
                CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(number, '–', '-'), '.', 1), '-', 1) AS UNSIGNED) ASC,
                CASE
                    WHEN LOCATE('.', number) > 0
                        THEN CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(number, '.', -1), ' ', 1) AS UNSIGNED)
                    WHEN LOCATE('-', REPLACE(number, '–', '-')) > 0
                        THEN CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(number, '–', '-'), '-', -1), ' ', 1) AS UNSIGNED)
                    ELSE 0
                END ASC,
                IF(number LIKE '%مكرر%', 1, 0) ASC
            ")
            ->paginate(50);

        return response()->json($articles);
    }

    public function show(Article $article)
    {
        $article->load(['code:id,slug,title_ar', 'section:id,title_ar']);
        return response()->json($article);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code_id'    => 'required|uuid|exists:codes,id',
            'number'     => 'required|string|max:20',
            'content_ar' => 'required|string',
            'content_fr' => 'nullable|string',
            'status'     => 'nullable|in:in_force,abrogated,amended,draft',
        ]);

        $code = Code::findOrFail($data['code_id']);

        // Ensure number is unique within this code
        $exists = Article::where('code_id', $data['code_id'])
            ->where('number', $data['number'])
            ->exists();
        if ($exists) {
            return response()->json([
                'message' => 'هذا الرقم موجود بالفعل في هذا القانون',
                'errors'  => ['number' => ['الرقم ' . $data['number'] . ' موجود مسبقاً']],
            ], 422);
        }

        $data['id']         = (string) \Illuminate\Support\Str::uuid();
        $data['slug']       = $code->slug . '-' . \Illuminate\Support\Str::slug($data['number']);
        $data['number_int'] = (int) $data['number'];
        $data['status']     = $data['status'] ?? 'in_force';

        // Make slug unique if collision
        $base = $data['slug'];
        $i = 1;
        while (Article::where('slug', $data['slug'])->exists()) {
            $data['slug'] = $base . '-' . $i++;
        }

        $article = Article::create($data);
        $code->increment('total_articles');

        $article->load('code:id,slug,title_ar');
        return response()->json(['message' => 'تمت إضافة المادة', 'article' => $article], 201);
    }

    public function update(Request $request, Article $article)
    {
        $data = $request->validate([
            'content_ar' => 'sometimes|string',
            'content_fr' => 'nullable|string',
            'status'     => 'sometimes|in:in_force,abrogated,amended,draft',
            'number'     => 'sometimes|string|max:20',
        ]);

        $article->update($data);

        return response()->json(['message' => 'تم تحديث المادة', 'article' => $article]);
    }

    public function destroy(Article $article)
    {
        $article->delete();
        return response()->json(['message' => 'تم حذف المادة']);
    }

    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'ids'    => 'required|array',
            'ids.*'  => 'uuid',
            'status' => 'required|in:in_force,abrogated,amended,draft',
        ]);

        Article::whereIn('id', $request->ids)->update(['status' => $request->status]);

        return response()->json(['message' => 'تم تحديث ' . count($request->ids) . ' مادة']);
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'uuid',
        ]);

        $deleted = Article::whereIn('id', $request->ids)->delete();

        return response()->json(['message' => "تم حذف {$deleted} مادة"]);
    }

    public function stats()
    {
        return response()->json([
            'total'       => Article::count(),
            'in_force'    => Article::where('status', 'in_force')->count(),
            'abrogated'   => Article::where('status', 'abrogated')->count(),
            'amended'     => Article::where('status', 'amended')->count(),
            'by_code'     => Code::withCount('articles')->get()
                                 ->map(fn($c) => ['name' => $c->title_ar, 'count' => $c->articles_count]),
        ]);
    }
}
