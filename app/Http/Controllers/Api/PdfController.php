<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Code;
use App\Models\PdfDocument;

class PdfController extends Controller
{
    /**
     * GET /api/v1/pdfs
     * Liste les PDFs publics avec source_url (lien officiel) seulement.
     */
    public function index()
    {
        $pdfs = PdfDocument::public()
            ->with('code:id,slug,title_ar')
            ->select('id', 'code_id', 'title_ar', 'title_fr', 'document_type',
                     'file_size', 'articles_extracted', 'source_url', 'created_at')
            ->whereNotNull('source_url')
            ->orderByDesc('created_at')
            ->paginate(20);

        $pdfs->through(fn($pdf) => $pdf->append([]));   // no stored file exposed

        return response()->json($pdfs);
    }

    /**
     * GET /api/v1/codes/{code}/pdfs
     * PDFs d'un code avec leur lien officiel pour les utilisateurs.
     */
    public function byCode(Code $code)
    {
        $pdfs = PdfDocument::public()
            ->where('code_id', $code->id)
            ->select('id', 'code_id', 'title_ar', 'title_fr', 'document_type',
                     'file_size', 'articles_extracted', 'source_url', 'created_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($pdf) {
                $pdf->file_size_human = $pdf->fileSizeForHumans();
                return $pdf;
            });

        return response()->json([
            'code' => $code->only(['id', 'slug', 'title_ar', 'title_fr']),
            'pdfs' => $pdfs,
        ]);
    }

    /**
     * GET /api/v1/pdfs/{id}/download
     * Redirige vers source_url (lien officiel).
     * Le fichier interne n'est jamais exposé aux utilisateurs.
     */
    public function download(PdfDocument $pdfDocument)
    {
        abort_if(!$pdfDocument->is_public, 403);

        if ($pdfDocument->source_url) {
            return redirect()->away($pdfDocument->source_url);
        }

        abort(404, 'Aucun lien officiel disponible pour ce document.');
    }

    /**
     * GET /api/v1/pdfs/{id}/view
     * Alias de download pour les PDFs officiels.
     */
    public function view(PdfDocument $pdfDocument)
    {
        return $this->download($pdfDocument);
    }
}
