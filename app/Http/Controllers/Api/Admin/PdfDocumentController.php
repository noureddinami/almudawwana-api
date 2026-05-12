<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Code;
use App\Models\PdfDocument;
use App\Services\PdfExtractionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PdfDocumentController extends Controller
{
    public function __construct(private PdfExtractionService $extractor) {}

    // ─────────────────────────────────────────────────────────
    // GET /api/v1/admin/pdfs
    // ─────────────────────────────────────────────────────────
    public function index()
    {
        $pdfs = PdfDocument::with('code:id,slug,title_ar', 'uploader:id,full_name')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($pdfs);
    }

    // ─────────────────────────────────────────────────────────
    // POST /api/v1/admin/pdfs
    // Accepts: .pdf, .md, .txt
    // ─────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'file'          => [
                'required', 'file',
                'mimetypes:application/pdf,text/plain,text/markdown,text/x-markdown',
                'max:51200',
            ],
            'title_ar'      => 'required|string|max:300',
            'title_fr'      => 'nullable|string|max:300',
            'code_id'       => 'nullable|uuid|exists:codes,id',
            'document_type' => 'nullable|in:code,law,decree,order,circular,other',
            'source_url'    => 'nullable|url|max:1000',
            'is_public'     => 'nullable|boolean',
        ]);

        $file             = $request->file('file');
        $originalName     = $file->getClientOriginalName();
        $ext              = strtolower($file->getClientOriginalExtension());

        // Enforce allowed extensions (mimetypes validator allows text/plain for any text file)
        if (!in_array($ext, ['pdf', 'md', 'txt'])) {
            return response()->json(['message' => 'Format non supporté. Utilisez .pdf, .md ou .txt'], 422);
        }

        $storedFilename = Str::uuid() . '.' . $ext;
        $file->storeAs('pdfs', $storedFilename, 'public');

        $pdf = PdfDocument::create([
            'id'                => Str::uuid(),
            'code_id'           => $request->code_id,
            'uploaded_by'       => $request->user()->id,
            'title_ar'          => $request->title_ar,
            'title_fr'          => $request->title_fr,
            'original_filename' => $originalName,
            'stored_filename'   => $storedFilename,
            'disk'              => 'public',
            'file_size'         => $file->getSize(),
            'status'            => 'pending',
            'document_type'     => $request->input('document_type', 'code'),
            'source_url'        => $request->source_url,
            'is_public'         => $request->input('is_public', true),
        ]);

        return response()->json([
            'message'      => 'Fichier uploadé avec succès',
            'pdf'          => $pdf,
            'download_url' => $pdf->getDownloadUrl(),
        ], 201);
    }

    // ─────────────────────────────────────────────────────────
    // GET /api/v1/admin/pdfs/{id}
    // ─────────────────────────────────────────────────────────
    public function show(PdfDocument $pdfDocument)
    {
        $pdfDocument->load('code:id,slug,title_ar,total_articles', 'uploader:id,full_name');

        return response()->json([
            'pdf'          => $pdfDocument,
            'download_url' => $pdfDocument->getDownloadUrl(),
            'file_size'    => $pdfDocument->fileSizeForHumans(),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // POST /api/v1/admin/pdfs/{id}/extract
    // ─────────────────────────────────────────────────────────
    public function extract(PdfDocument $pdfDocument)
    {
        if (!$pdfDocument->code_id) {
            return response()->json([
                'message' => 'Associez ce fichier à un code juridique avant d\'extraire.',
            ], 422);
        }

        if ($pdfDocument->status === 'processing') {
            return response()->json(['message' => 'Extraction déjà en cours.'], 409);
        }

        $pdfDocument->update(['status' => 'processing', 'extraction_log' => null]);

        try {
            $filePath = $pdfDocument->getFullPath();

            if (!file_exists($filePath)) {
                throw new \RuntimeException("Fichier introuvable : {$filePath}");
            }

            $ext      = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $code     = Code::findOrFail($pdfDocument->code_id);
            $articles = $this->extractor->extractArticles($filePath);
            $imported = $this->extractor->importToDatabase($pdfDocument, $code, $articles);

            // Detect which strategy was used for the log
            $strategy = in_array($ext, ['md', 'txt']) ? 'texte/markdown' : 'PDF';

            $log = sprintf(
                "Stratégie : %s\n%d articles détectés.\n%d articles importés.\n%d doublons ignorés.",
                $strategy,
                count($articles),
                $imported,
                count($articles) - $imported
            );

            $pdfDocument->update([
                'status'             => 'imported',
                'articles_extracted' => $imported,
                'extraction_log'     => $log,
            ]);

            return response()->json([
                'message'           => 'Extraction terminée',
                'articles_detected' => count($articles),
                'articles_imported' => $imported,
                'log'               => $log,
            ]);

        } catch (\Exception $e) {
            $pdfDocument->update([
                'status'         => 'failed',
                'extraction_log' => 'Erreur : ' . $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Extraction échouée',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────
    // POST /api/v1/admin/pdfs/{id}/preview
    // Dry-run: returns detected articles without importing
    // ─────────────────────────────────────────────────────────
    public function preview(PdfDocument $pdfDocument)
    {
        $filePath = $pdfDocument->getFullPath();

        if (!file_exists($filePath)) {
            return response()->json(['message' => 'Fichier introuvable'], 404);
        }

        try {
            $articles = $this->extractor->extractArticles($filePath);

            return response()->json([
                'detected'  => count($articles),
                'sample'    => array_slice($articles, 0, 5),
                'all_numbers' => array_column($articles, 'number'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────
    // PUT /api/v1/admin/pdfs/{id}
    // ─────────────────────────────────────────────────────────
    public function update(Request $request, PdfDocument $pdfDocument)
    {
        $request->validate([
            'title_ar'      => 'sometimes|string|max:300',
            'title_fr'      => 'nullable|string|max:300',
            'code_id'       => 'nullable|uuid|exists:codes,id',
            'document_type' => 'nullable|in:code,law,decree,order,circular,other',
            'is_public'     => 'nullable|boolean',
        ]);

        $pdfDocument->update($request->only([
            'title_ar', 'title_fr', 'code_id', 'document_type', 'is_public',
        ]));

        return response()->json(['message' => 'Fichier mis à jour', 'pdf' => $pdfDocument->fresh()]);
    }

    // ─────────────────────────────────────────────────────────
    // DELETE /api/v1/admin/pdfs/{id}
    // ─────────────────────────────────────────────────────────
    public function destroy(PdfDocument $pdfDocument)
    {
        Storage::disk($pdfDocument->disk)->delete('pdfs/' . $pdfDocument->stored_filename);
        $pdfDocument->delete();

        return response()->json(['message' => 'Fichier supprimé']);
    }
}
