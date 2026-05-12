<?php

namespace App\Console\Commands;

use App\Models\PdfDocument;
use App\Services\PdfExtractionService;
use Illuminate\Console\Command;

class ExtractPdfArticles extends Command
{
    protected $signature   = 'pdf:extract {--id= : UUID d\'un PDF spécifique} {--all : Extraire tous les PDFs pending}';
    protected $description = 'Extrait les articles des PDFs et les importe en base';

    public function __construct(private PdfExtractionService $extractor)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = PdfDocument::whereNotNull('code_id');

        if ($this->option('id')) {
            $query->where('id', $this->option('id'));
        } elseif ($this->option('all')) {
            $query->where('status', 'pending');
        } else {
            $query->where('status', 'pending');
        }

        $pdfs = $query->with('code')->get();

        if ($pdfs->isEmpty()) {
            $this->warn('Aucun PDF à traiter.');
            return self::SUCCESS;
        }

        $this->info("📄 {$pdfs->count()} PDF(s) à traiter\n");

        foreach ($pdfs as $pdf) {
            $this->line("▶ <info>{$pdf->title_ar}</info>");
            $this->line("   Code : {$pdf->code->title_ar}");

            $pdfPath = $pdf->getFullPath();

            if (!file_exists($pdfPath)) {
                $this->error("   ❌ Fichier introuvable : {$pdfPath}");
                $pdf->update(['status' => 'failed', 'extraction_log' => 'Fichier introuvable']);
                continue;
            }

            $pdf->update(['status' => 'processing']);

            try {
                $this->line("   ⏳ Extraction du texte...");
                $articles = $this->extractor->extractArticles($pdfPath);
                $this->line("   📑 {$this->countArticles($articles)} articles détectés");

                $this->line("   💾 Import en base...");
                $imported = $this->extractor->importToDatabase($pdf, $pdf->code, $articles);

                $skipped = count($articles) - $imported;
                $log     = count($articles) . " détectés · {$imported} importés · {$skipped} doublons ignorés";

                $pdf->update([
                    'status'             => 'imported',
                    'articles_extracted' => $imported,
                    'extraction_log'     => $log,
                ]);

                $this->info("   ✅ {$imported} articles importés" . ($skipped ? " ({$skipped} doublons ignorés)" : ''));

            } catch (\Exception $e) {
                $pdf->update(['status' => 'failed', 'extraction_log' => $e->getMessage()]);
                $this->error("   ❌ Erreur : " . $e->getMessage());
            }

            $this->newLine();
        }

        // Récapitulatif
        $total = \App\Models\Article::count();
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📊 Total articles en base : {$total}");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        return self::SUCCESS;
    }

    private function countArticles(array $articles): int
    {
        return count($articles);
    }
}
