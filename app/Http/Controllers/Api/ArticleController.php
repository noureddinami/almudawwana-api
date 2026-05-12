<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Code;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    /**
     * GET /api/v1/articles/{slug}
     * Détail d'un article + commentaires approuvés + jurisprudence
     */
    public function show(Article $article)
    {
        $article->incrementViewCount();

        $article->load([
            'code:id,slug,title_ar,title_fr',
            'section:id,title_ar,number',
            'tags:id,name_ar,name_fr,slug',
        ]);

        $adminNotes = $article->commentaries()
            ->where('type', 'annotation')
            ->where('status', 'approved')
            ->with('author:id,full_name')
            ->orderBy('created_at')
            ->get(['id', 'article_id', 'author_id', 'content_ar', 'created_at']);

        return response()->json(array_merge($article->toArray(), ['admin_notes' => $adminNotes]));
    }

    /**
     * GET /api/v1/search?q=...&code=...
     * Recherche fulltext en arabe (MySQL LIKE — Meilisearch en Phase 2)
     */
    public function search(Request $request)
    {
        $request->validate([
            'q'  => 'required_without:kw|nullable|string|min:1|max:200',
            'kw' => 'required_without:q|nullable|string|min:1|max:500',
        ]);

        $codeSlug = $request->input('code');
        $perPage  = min((int) $request->input('per_page', 20), 50);

        // ── Tab 3: multi-keyword search ──────────────────────────────────────
        if ($request->filled('kw')) {
            $words = array_values(array_filter(
                preg_split('/[,،\s]+/u', trim($request->kw)),
                fn($w) => mb_strlen($w) >= 2
            ));

            $results = Article::inForce()
                ->select('id', 'code_id', 'number', 'number_int', 'slug',
                         'content_ar', 'status', 'view_count', 'comment_count')
                ->when(!empty($words), function ($q) use ($words) {
                    $q->where(function ($sq) use ($words) {
                        foreach ($words as $word) {
                            $sq->orWhere('content_ar', 'like', '%' . $word . '%');
                        }
                    });
                })
                ->when($codeSlug, function ($q) use ($codeSlug) {
                    $code = Code::where('slug', $codeSlug)->first();
                    if ($code) $q->where('code_id', $code->id);
                })
                ->with(['code:id,slug,title_ar'])
                ->orderBy('view_count', 'desc')
                ->paginate($perPage);

            return response()->json([
                'query'   => $request->kw,
                'results' => $results,
            ]);
        }

        // ── Tab 1 & 2: q-based search ────────────────────────────────────────
        $raw      = trim($request->q);
        $textQuery     = $raw;
        $articleNumber = null;

        $patternArticle = '/^(?:الفصل|المادة|البند|فصل|مادة|بند)\s*(\d[\d\-–]*(?:\s*مكرر)?)/u';
        $patternNumber  = '/^\d[\d\-–]*$/';

        if (preg_match($patternArticle, $raw, $m)) {
            $articleNumber = trim($m[1]);
            $textQuery     = null;
        } elseif (preg_match($patternNumber, $raw)) {
            $articleNumber = $raw;
            $textQuery     = null;
        }

        $results = Article::inForce()
            ->select('id', 'code_id', 'number', 'number_int', 'slug',
                     'content_ar', 'status', 'view_count', 'comment_count')
            ->when($articleNumber, function ($q) use ($articleNumber) {
                // Match exact number OR sub-articles (49 → 49, 49.1, 49-1, 49 مكرر)
                // REGEXP ensures the next char after the number is a separator, not another digit
                $q->where(function ($sq) use ($articleNumber) {
                    $sq->where('number', $articleNumber)
                       ->orWhereRaw(
                           'number REGEXP ?',
                           ['^' . preg_quote($articleNumber, null) . '[^0-9]']
                       );
                });
            })
            ->when($textQuery, function ($q) use ($textQuery) {
                $q->where(function ($sq) use ($textQuery) {
                    $sq->where('content_ar', 'like', '%' . $textQuery . '%')
                       ->orWhere('number', 'like', '%' . $textQuery . '%');
                });
            })
            ->when($codeSlug, function ($q) use ($codeSlug) {
                $code = Code::where('slug', $codeSlug)->first();
                if ($code) $q->where('code_id', $code->id);
            })
            ->with(['code:id,slug,title_ar'])
            ->orderByRaw($articleNumber
                ? 'CAST(number_int AS UNSIGNED) ASC'
                : 'view_count DESC'
            )
            ->paginate($perPage);

        return response()->json([
            'query'          => $raw,
            'article_number' => $articleNumber,
            'results'        => $results,
        ]);
    }

    /**
     * GET /api/v1/codes/{codeSlug}/articles/{slug}
     * Article par slug dans un code donné
     */
    public function showInCode(Code $code, Article $article)
    {
        // Vérifier que l'article appartient bien au code
        abort_if($article->code_id !== $code->id, 404);

        return $this->show($article);
    }
}
