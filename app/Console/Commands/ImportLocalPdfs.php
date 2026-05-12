<?php

namespace App\Console\Commands;

use App\Models\Code;
use App\Models\PdfDocument;
use App\Services\PdfExtractionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportLocalPdfs extends Command
{
    protected $signature = 'pdf:import
                            {--path= : Dossier source (défaut: resources/pdf)}
                            {--extract : Extraire les articles automatiquement après import}
                            {--code= : Slug du code juridique à associer}';

    protected $description = 'Importe les PDFs locaux depuis resources/pdf dans la base de données';

    public function __construct(private PdfExtractionService $extractor)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $sourcePath = $this->option('path') ?? resource_path('pdf');
        $extract    = $this->option('extract');
        $codeSlug   = $this->option('code');

        if (!is_dir($sourcePath)) {
            $this->error("Dossier introuvable : {$sourcePath}");
            return self::FAILURE;
        }

        $pdfFiles = glob($sourcePath . DIRECTORY_SEPARATOR . '*.pdf');

        if (empty($pdfFiles)) {
            $this->warn("Aucun PDF trouvé dans : {$sourcePath}");
            return self::SUCCESS;
        }

        $this->info("📂 " . count($pdfFiles) . " PDF(s) trouvé(s) dans {$sourcePath}");
        $this->newLine();

        // Résoudre le code si fourni
        $code = null;
        if ($codeSlug) {
            $code = Code::where('slug', $codeSlug)->first();
            if (!$code) {
                $this->warn("Code '{$codeSlug}' introuvable — les PDFs seront importés sans code associé.");
            } else {
                $this->info("📖 Code associé : {$code->title_ar}");
            }
        }

        $bar = $this->output->createProgressBar(count($pdfFiles));
        $bar->start();

        $imported  = 0;
        $extracted = 0;
        $errors    = [];

        foreach ($pdfFiles as $pdfPath) {
            $filename = basename($pdfPath);

            // Éviter les doublons
            if (PdfDocument::where('original_filename', $filename)->exists()) {
                $this->newLine();
                $this->warn("  ⏩ Déjà importé : {$filename}");
                $bar->advance();
                continue;
            }

            try {
                // Copier dans storage/public/pdfs/
                $storedFilename = Str::uuid() . '.pdf';
                $contents = file_get_contents($pdfPath);
                Storage::disk('public')->put('pdfs/' . $storedFilename, $contents);

                // Titrer depuis le nom de fichier
                $titleAr = pathinfo($filename, PATHINFO_FILENAME);
                $titleAr = preg_replace('/[-_]+/', ' ', $titleAr);

                $pdf = PdfDocument::create([
                    'id'                => Str::uuid(),
                    'code_id'           => $code?->id,
                    'title_ar'          => $titleAr,
                    'original_filename' => $filename,
                    'stored_filename'   => $storedFilename,
                    'disk'              => 'public',
                    'file_size'         => filesize($pdfPath),
                    'status'            => 'pending',
                    'document_type'     => 'code',
                    'is_public'         => true,
                    'source_url'        => 'adala.justice.gov.ma',
                ]);

                $imported++;

                // Extraction automatique si demandée et code associé
                if ($extract && $code) {
                    $pdf->update(['status' => 'processing']);
                    $articles = $this->extractor->extractArticles($pdfPath);
                    $count    = $this->extractor->importToDatabase($pdf, $code, $articles);
                    $pdf->update([
                        'status'             => 'imported',
                        'articles_extracted' => $count,
                        'extraction_log'     => count($articles) . " détectés, {$count} importés.",
                    ]);
                    $extracted += $count;
                }

            } catch (\Exception $e) {
                $errors[] = "{$filename} : " . $e->getMessage();
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ {$imported} PDF(s) importé(s)");
        if ($extract) {
            $this->info("📑 {$extracted} article(s) extrait(s) et importé(s) en base");
        }

        if (!empty($errors)) {
            $this->newLine();
            $this->error("⚠️  Erreurs :");
            foreach ($errors as $err) {
                $this->line("  - {$err}");
            }
        }

        $this->newLine();
        $this->line("💡 Pour extraire les articles depuis l'admin :");
        $this->line("   POST /api/v1/admin/pdfs/{id}/extract");

        return self::SUCCESS;
    }
}
