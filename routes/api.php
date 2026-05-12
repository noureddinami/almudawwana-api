<?php

use App\Http\Controllers\Api\Admin\AdminArticleController;
use App\Http\Controllers\Api\Admin\AdminCodeTypeController;
use App\Http\Controllers\Api\Admin\AdminCodeController;
use App\Http\Controllers\Api\Admin\AdminCommentaryController;
use App\Http\Controllers\Api\Admin\AdminStatsController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\PdfDocumentController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CodeController;
use App\Http\Controllers\Api\CommentaryController;
use App\Http\Controllers\Api\PdfController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ── Auth public ──────────────────────────────────────────
    Route::post('/auth/register',       [AuthController::class, 'register']);
    Route::post('/auth/login',          [AuthController::class, 'login']);
    Route::get('/auth/google/redirect', [AuthController::class, 'googleRedirect']);
    Route::get('/auth/google/callback', [AuthController::class, 'googleCallback']);

    // ── Types de codes (public) ──────────────────────────────
    Route::get('/code-types', fn() =>
        response()->json(\App\Models\CodeType::orderBy('sort_order')->orderBy('id')->get())
    );

    // ── Codes juridiques (lecture publique) ──────────────────
    Route::get('/codes',                           [CodeController::class, 'index']);
    Route::get('/codes/{code}',                    [CodeController::class, 'show']);
    Route::get('/codes/{code}/articles',           [CodeController::class, 'articles']);
    Route::get('/codes/{code}/articles/{article}', [ArticleController::class, 'showInCode']);
    Route::get('/codes/{code}/pdfs',               [PdfController::class, 'byCode']);

    // ── Articles ─────────────────────────────────────────────
    Route::get('/articles/{article}',          [ArticleController::class, 'show']);

    // ── Dernières notes admins (public) ──────────────────────
    Route::get('/notes/recent', function () {
        $notes = \App\Models\Commentary::where('type', 'annotation')
            ->where('status', 'approved')
            ->with([
                'article:id,slug,number,code_id',
                'article.code:id,slug,title_ar',
            ])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get(['id', 'article_id', 'content_ar', 'created_at']);
        return response()->json($notes);
    });
    Route::get('/articles/{article}/comments', [CommentaryController::class, 'index']);

    // ── Recherche fulltext ───────────────────────────────────
    Route::get('/search',              [ArticleController::class, 'search']);

    // ── PDFs publics ─────────────────────────────────────────
    Route::get('/pdfs',                [PdfController::class, 'index']);
    Route::get('/pdfs/{pdfDocument}/download', [PdfController::class, 'download']);
    Route::get('/pdfs/{pdfDocument}/view',     [PdfController::class, 'view']);

    // ── Auth protégé ─────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/articles/{article}/comments', [CommentaryController::class, 'store']);
        Route::post('/auth/logout',          [AuthController::class, 'logout']);
        Route::post('/auth/verify-password', [AuthController::class, 'verifyPassword']);
        Route::get('/me',                    [AuthController::class, 'me']);
        Route::put('/me',                    [AuthController::class, 'update']);
    });

    // ── ADMIN (auth:sanctum + role admin/moderator) ──────────
    Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin,moderator'])->group(function () {

        // Dashboard stats
        Route::get('/stats',          [AdminStatsController::class, 'dashboard']);

        // Utilisateurs
        Route::get('/users',          [AdminUserController::class, 'index']);
        Route::get('/users/stats',    [AdminUserController::class, 'stats']);
        Route::get('/users/{user}',   [AdminUserController::class, 'show']);
        Route::put('/users/{user}',   [AdminUserController::class, 'update']);
        Route::delete('/users/{user}',[AdminUserController::class, 'destroy']);

        // Types de codes
        Route::get('/code-types',                  [AdminCodeTypeController::class, 'index']);
        Route::post('/code-types',                 [AdminCodeTypeController::class, 'store']);
        Route::put('/code-types/{codeType}',       [AdminCodeTypeController::class, 'update']);
        Route::delete('/code-types/{codeType}',    [AdminCodeTypeController::class, 'destroy']);

        // Codes juridiques  (binding par id car le frontend envoie des UUIDs)
        Route::get('/codes',               [AdminCodeController::class, 'index']);
        Route::post('/codes',              [AdminCodeController::class, 'store']);
        Route::put('/codes/{code:id}',     [AdminCodeController::class, 'update']);
        Route::delete('/codes/{code:id}',  [AdminCodeController::class, 'destroy']);

        // Articles  (binding par id)
        Route::get('/articles',                       [AdminArticleController::class, 'index']);
        Route::post('/articles',                      [AdminArticleController::class, 'store']);
        Route::get('/articles/stats',                 [AdminArticleController::class, 'stats']);
        Route::post('/articles/bulk-status',          [AdminArticleController::class, 'bulkUpdateStatus']);
        Route::post('/articles/bulk-delete',          [AdminArticleController::class, 'bulkDestroy']);
        Route::get('/articles/{article:id}',          [AdminArticleController::class, 'show']);
        Route::put('/articles/{article:id}',          [AdminArticleController::class, 'update']);
        Route::delete('/articles/{article:id}',       [AdminArticleController::class, 'destroy']);

        // Commentaires utilisateurs (modération)
        Route::get('/comments',                                  [AdminCommentaryController::class, 'index']);
        Route::put('/comments/{commentary}',                     [AdminCommentaryController::class, 'update']);
        Route::delete('/comments/{commentary}',                  [AdminCommentaryController::class, 'destroy']);

        // Notes admin par article (binding par id)
        Route::get('/articles/{article:id}/notes',               [AdminCommentaryController::class, 'articleNotes']);
        Route::post('/articles/{article:id}/notes',              [AdminCommentaryController::class, 'storeNote']);
        Route::delete('/notes/{commentary}',                     [AdminCommentaryController::class, 'destroyNote']);

        // PDFs  (PdfDocument utilise l'id par défaut — pas de getRouteKeyName override)
        Route::get('/pdfs',                           [PdfDocumentController::class, 'index']);
        Route::post('/pdfs',                          [PdfDocumentController::class, 'store']);
        Route::get('/pdfs/{pdfDocument}',             [PdfDocumentController::class, 'show']);
        Route::put('/pdfs/{pdfDocument}',             [PdfDocumentController::class, 'update']);
        Route::delete('/pdfs/{pdfDocument}',          [PdfDocumentController::class, 'destroy']);
        Route::post('/pdfs/{pdfDocument}/extract',    [PdfDocumentController::class, 'extract']);
        Route::post('/pdfs/{pdfDocument}/preview',    [PdfDocumentController::class, 'preview']);
    });

});
