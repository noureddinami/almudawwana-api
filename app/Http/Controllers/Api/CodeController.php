<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Code;
use Illuminate\Http\Request;

class CodeController extends Controller
{
    /**
     * GET /api/v1/codes
     * Liste tous les codes juridiques en vigueur
     */
    public function index(Request $request)
    {
        $query = Code::inForce()
            ->select('id', 'slug', 'title_ar', 'title_fr', 'type', 'status',
                     'official_number', 'total_articles', 'promulgation_date', 'created_at');

        // Filtre par type
        if ($request->has('type')) {
            $query->ofType($request->type);
        }

        // Recherche simple dans le titre
        if ($request->has('q')) {
            $query->where('title_ar', 'like', '%' . $request->q . '%')
                  ->orWhere('title_fr', 'like', '%' . $request->q . '%');
        }

        $order = $request->input('sort') === 'latest' ? 'created_at' : 'title_ar';
        $codes = $query->orderBy($order, 'desc')->paginate((int) $request->input('per_page', 20));

        return response()->json($codes);
    }

    /**
     * GET /api/v1/codes/{slug}
     * Détail d'un code avec ses livres et sections
     */
    public function show(Code $code)
    {
        $code->load([
            'books' => function ($q) {
                $q->select('id', 'code_id', 'number', 'title_ar', 'title_fr', 'display_order');
            },
            'sections' => function ($q) {
                $q->whereNull('parent_id')
                  ->select('id', 'code_id', 'book_id', 'parent_id', 'number',
                           'title_ar', 'title_fr', 'level', 'display_order')
                  ->with(['children' => function ($q2) {
                      $q2->select('id', 'code_id', 'book_id', 'parent_id', 'number',
                                 'title_ar', 'title_fr', 'level', 'display_order');
                  }]);
            },
        ]);

        return response()->json($code);
    }

    /**
     * GET /api/v1/codes/{slug}/articles
     * Articles d'un code (paginés)
     */
    public function articles(Request $request, Code $code)
    {
        $articles = $code->articles()
            ->inForce()
            ->select('id', 'code_id', 'section_id', 'number', 'number_int',
                     'slug', 'content_ar', 'status', 'view_count', 'comment_count')
            ->with(['section:id,title_ar,number'])
            ->orderByRaw("
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
}
