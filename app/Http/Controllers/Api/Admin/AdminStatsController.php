<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Code;
use App\Models\Commentary;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminStatsController extends Controller
{
    public function dashboard()
    {
        $now = now();

        return response()->json([

            // ── KPI globaux ─────────────────────────────────────────────
            'users' => [
                'total'     => User::count(),
                'active'    => User::where('status', 'active')->count(),
                'new_week'  => User::where('created_at', '>=', $now->copy()->subWeek())->count(),
                'new_month' => User::where('created_at', '>=', $now->copy()->subMonth())->count(),
            ],
            'codes' => [
                'total'    => Code::count(),
                'in_force' => Code::where('status', 'in_force')->count(),
            ],
            'articles' => [
                'total'     => Article::count(),
                'in_force'  => Article::where('status', 'in_force')->count(),
                'amended'   => Article::where('status', 'amended')->count(),
                'abrogated' => Article::where('status', 'abrogated')->count(),
                'draft'     => Article::where('status', 'draft')->count(),
                'total_views' => (int) Article::sum('view_count'),
            ],
            'comments' => [
                'total'    => Commentary::where('type', 'commentary')->count(),
                'pending'  => Commentary::where('type', 'commentary')->where('status', 'pending')->count(),
                'approved' => Commentary::where('type', 'commentary')->where('status', 'approved')->count(),
                'rejected' => Commentary::where('type', 'commentary')->where('status', 'rejected')->count(),
            ],
            'notes' => [
                'total' => Commentary::where('type', 'annotation')->count(),
            ],

            // ── Top 7 articles les plus vus ─────────────────────────────
            'top_viewed' => Article::with('code:id,slug,title_ar')
                ->select('id', 'code_id', 'number', 'slug', 'view_count', 'status')
                ->orderByDesc('view_count')
                ->limit(7)
                ->get(),

            // ── Répartition par code ────────────────────────────────────
            'codes_breakdown' => Code::select('id', 'title_ar', 'slug', 'total_articles', 'status', 'type')
                ->where('total_articles', '>', 0)
                ->orderByDesc('total_articles')
                ->limit(10)
                ->get(),

            // ── Derniers inscrits ───────────────────────────────────────
            'recent_users' => User::select('id', 'full_name', 'email', 'role', 'status', 'created_at')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(),

            // ── Derniers commentaires en attente ────────────────────────
            'pending_comments' => Commentary::where('type', 'commentary')
                ->where('status', 'pending')
                ->with([
                    'author:id,full_name,email',
                    'article:id,number,code_id,slug',
                    'article.code:id,slug,title_ar',
                ])
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'article_id', 'author_id', 'content_ar', 'created_at']),

            // ── Activité des 7 derniers jours (articles créés par jour) ──
            'activity_week' => Article::select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as count')
                )
                ->where('created_at', '>=', $now->copy()->subDays(6)->startOfDay())
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
        ]);
    }
}
