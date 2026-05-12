<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Code;
use App\Models\PdfDocument;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;

/**
 * Extraction engine with three strategies, tried in order:
 *
 *  1. Text/Markdown  – .md / .txt files: plain Unicode, regex on correct Arabic order
 *  2. pdftotext CLI  – Poppler's pdftotext: best Arabic BiDi support for digitized PDFs
 *  3. smalot fallback – last resort for PDFs when Poppler isn't available
 */
class PdfExtractionService
{
    // ── Patterns for text/markdown (correct Unicode order) ────────────────────

    /** Matches article header lines in correct Arabic Unicode order */
    private const TEXT_HEADER = '/^(?:#{1,4}\s*)?(?:\*{1,2})?(الفصل|المادة|البند)\s+(\d[\d\-–]*(?:\s*مكرر)?)\b/u';

    /** Matches "الأولى / الثانية …" ordinal forms */
    private const TEXT_ORDINAL = '/^(?:#{1,4}\s*)?(?:\*{1,2})?(المادة|الفصل)\s+(الأولى|الأول|الثانية|الثاني|الثالثة|الثالث|الرابعة|الرابع|الخامسة|الخامس)\b/u';

    private const ORDINAL_MAP = [
        'الأولى' => '1', 'الأول' => '1',
        'الثانية' => '2', 'الثاني' => '2',
        'الثالثة' => '3', 'الثالث' => '3',
        'الرابعة' => '4', 'الرابع' => '4',
        'الخامسة' => '5', 'الخامس' => '5',
    ];

    // ── Patterns for smalot visual-order fallback ──────────────────────────────

    private array $visualPatterns = [
        'فصل_composé' => '/^\s*لصفلا(\d+[-–]\d+)\.?\s*[-–]/u',
        'مادة_composé' => '/^\s*ةداملا(\d+[-–]\d+)\.?\s*/u',
        'فصل_point'   => '/^\s*لصفلا(\d+)\.?\s*[-–]/u',
        'مادة_point'  => '/^\s*ةداملا(\d+)\.?\s*[-–]/u',
        'فصل'         => '/^\s*لصفلا\s+(\d+(?:\s*ررك\s*م)?)/u',
        'مادة'        => '/^\s*ةداملا\s+(\d+(?:\s*ررك\s*م)?)/u',
        'بند'         => '/^\s*دنبلا\s+(\d+(?:\s*ررك\s*م)?)/u',
        'فصل_collé'   => '/^\s*لصفلا(\d+)\s*$/u',
        'مادة_collé'  => '/^\s*ةداملا(\d+)\s*$/u',
        'بند_collé'   => '/^\s*دنبلا(\d+)\s*$/u',
        'مادة_ordinal'=> '/^\s*(ةيناثلا|ىلولأا|ثلاثلا|عبارلا|سماخلا)\s+ةداملا\s*$/u',
    ];

    private array $visualTypeMap = [
        'فصل_composé' => 'الفصل', 'مادة_composé' => 'المادة',
        'فصل_point'   => 'الفصل', 'مادة_point'   => 'المادة',
        'فصل'         => 'الفصل', 'فصل_collé'    => 'الفصل',
        'مادة'        => 'المادة', 'مادة_collé'   => 'المادة',
        'بند'         => 'البند',  'بند_collé'    => 'البند',
        'مادة_ordinal'=> 'المادة',
    ];

    private array $visualOrdinal = [
        'ىلولأا' => '1', 'ةيناثلا' => '2',
        'ثلاثلا' => '3', 'عبارلا'  => '4', 'سماخلا' => '5',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Detects file type and dispatches to the right parser.
     * Returns array of ['type', 'number', 'number_int', 'content_ar'].
     */
    public function extractArticles(string $filePath): array
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($ext, ['md', 'txt'])) {
            $text = file_get_contents($filePath);
            if ($text === false) {
                throw new \RuntimeException("Impossible de lire le fichier : {$filePath}");
            }
            return $this->parseTextArticles($this->normalizeText($text));
        }

        // PDF: try pdftotext first, fallback to smalot
        $text = $this->extractViaPdftotext($filePath);

        if ($text !== null) {
            $articles = $this->parseTextArticles($text);
            if (count($articles) > 0) {
                return $articles;
            }
            // pdftotext succeeded but no articles detected — fall through to smalot
        }

        return $this->extractViaSmalot($filePath);
    }

    /**
     * Imports extracted articles into the database.
     */
    public function importToDatabase(PdfDocument $pdf, Code $code, array $articles): int
    {
        $imported = 0;
        $source   = 'adala.justice.gov.ma';

        foreach ($articles as $item) {
            if (Article::where('code_id', $code->id)->where('number', $item['number'])->exists()) {
                continue;
            }

            $slug     = $code->slug . '-' . Str::slug($item['type'] . '-' . $item['number']);
            $baseSlug = $slug;
            $i        = 1;
            while (Article::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $i++;
            }

            Article::create([
                'id'         => Str::uuid(),
                'code_id'    => $code->id,
                'number'     => $item['number'],
                'number_int' => $item['number_int'],
                'slug'       => $slug,
                'content_ar' => $item['content_ar'],
                'status'     => 'in_force',
                'source'     => $source,
            ]);

            $imported++;
        }

        $code->update(['total_articles' => Article::where('code_id', $code->id)->count()]);

        return $imported;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Strategy 1 — Plain text / Markdown parser (Unicode, correct order)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Parses structured Arabic text where words are in logical Unicode order.
     * Works for .md, .txt, and pdftotext output.
     *
     * Supported header formats:
     *   الفصل 1            (bare)
     *   ## الفصل 1         (Markdown heading)
     *   الفصل 1 -          (with dash separator)
     *   المادة 3 مكرر       (bis)
     *   الفصل الأول         (ordinal)
     */
    public function parseTextArticles(string $text): array
    {
        $lines    = explode("\n", $text);
        $articles = [];

        $current        = null;   // ['type', 'number']
        $currentContent = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Skip empty and page-number-only lines
            if ($trimmed === '' || preg_match('/^\d+\s*$/', $trimmed)) {
                continue;
            }

            $header = $this->matchTextHeader($trimmed);

            if ($header !== null) {
                if ($current !== null && !empty($currentContent)) {
                    $this->flushArticle($current, implode("\n", $currentContent), $articles);
                }
                $current        = $header;
                $currentContent = [];
            } else {
                if ($current !== null) {
                    // Strip inline header if content starts on the same line as the header
                    $currentContent[] = $trimmed;
                }
            }
        }

        if ($current !== null && !empty($currentContent)) {
            $this->flushArticle($current, implode("\n", $currentContent), $articles);
        }

        return $articles;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Strategy 2 — pdftotext CLI (Poppler)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calls pdftotext (Poppler) via shell.
     * Returns null if the command is unavailable.
     */
    public function extractViaPdftotext(string $pdfPath): ?string
    {
        // Quick check: is pdftotext available?
        exec('pdftotext -v 2>&1', $out, $code);
        if ($code !== 0 && $code !== 99) {
            // 99 = usage print (pdftotext with no args), 0 = version printed
            // Any other non-zero likely means not installed
            $whichOut = [];
            exec('where pdftotext 2>&1', $whichOut, $whereCode);
            if ($whereCode !== 0) {
                exec('which pdftotext 2>&1', $whichOut, $whichCode);
                if ($whichCode !== 0) {
                    return null; // not installed
                }
            }
        }

        $escapedPath = escapeshellarg($pdfPath);
        $tmpFile     = sys_get_temp_dir() . DIRECTORY_SEPARATOR . Str::uuid() . '.txt';
        $escapedTmp  = escapeshellarg($tmpFile);

        // -enc UTF-8 ensures Unicode output; no -layout flag for better RTL flow
        exec("pdftotext -enc UTF-8 {$escapedPath} {$escapedTmp} 2>&1", $output, $exitCode);

        if ($exitCode !== 0 || !file_exists($tmpFile)) {
            @unlink($tmpFile);
            return null;
        }

        $text = file_get_contents($tmpFile);
        @unlink($tmpFile);

        if ($text === false || trim($text) === '') {
            return null;
        }

        return $this->normalizeText($text);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Strategy 3 — smalot/pdfparser fallback (visual order, reversed Arabic)
    // ─────────────────────────────────────────────────────────────────────────

    public function extractViaSmalot(string $pdfPath): array
    {
        try {
            $parser  = new Parser();
            $pdf     = $parser->parseFile($pdfPath);
            $rawText = $pdf->getText();
        } catch (\Exception $e) {
            throw new \RuntimeException("smalot: " . $e->getMessage());
        }

        $rawText  = $this->cleanRawText($rawText);
        $lines    = explode("\n", $rawText);
        $articles = [];

        $current        = null;
        $currentContent = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') continue;

            $match = $this->matchVisualHeader($trimmed);

            if ($match) {
                if ($current !== null && !empty($currentContent)) {
                    $fixed = array_map([$this, 'fixBidiLine'], $currentContent);
                    $fixed = array_filter($fixed, fn($l) =>
                        trim($l) !== '' && !preg_match('/^\d+\s*$/', trim($l))
                    );
                    $content = implode("\n", array_values($fixed));
                    if (mb_strlen($content) > 5) {
                        $articles[] = [
                            'type'       => $current['type'],
                            'number'     => $current['number'],
                            'number_int' => (int) $current['number'],
                            'content_ar' => $content,
                        ];
                    }
                }
                $current        = $match;
                $currentContent = [];
            } else {
                if ($current !== null) {
                    $currentContent[] = $trimmed;
                }
            }
        }

        if ($current !== null && !empty($currentContent)) {
            $fixed   = array_map([$this, 'fixBidiLine'], $currentContent);
            $fixed   = array_filter($fixed, fn($l) =>
                trim($l) !== '' && !preg_match('/^\d+\s*$/', trim($l))
            );
            $content = implode("\n", array_values($fixed));
            if (mb_strlen($content) > 5) {
                $articles[] = [
                    'type'       => $current['type'],
                    'number'     => $current['number'],
                    'number_int' => (int) $current['number'],
                    'content_ar' => $content,
                ];
            }
        }

        return $articles;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function matchTextHeader(string $line): ?array
    {
        // Try ordinal first (المادة الأولى)
        if (preg_match(self::TEXT_ORDINAL, $line, $m)) {
            return [
                'type'   => $m[1],
                'number' => self::ORDINAL_MAP[trim($m[2])] ?? '1',
            ];
        }

        // Standard numeric
        if (preg_match(self::TEXT_HEADER, $line, $m)) {
            $number = trim($m[2]);
            // Normalize مكرر spacing
            $number = preg_replace('/\s+مكرر/u', ' مكرر', $number);
            return ['type' => $m[1], 'number' => $number];
        }

        return null;
    }

    private function flushArticle(array $header, string $rawContent, array &$out): void
    {
        // Clean up content: remove Markdown syntax, normalize whitespace
        $content = preg_replace('/^#{1,4}\s*/mu', '', $rawContent);   // ## headings
        $content = preg_replace('/\*{1,3}([^*]+)\*{1,3}/u', '$1', $content); // **bold**
        $content = preg_replace('/ {2,}/', ' ', $content);
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        $content = trim($content);

        if (mb_strlen($content) > 5) {
            $out[] = [
                'type'       => $header['type'],
                'number'     => $header['number'],
                'number_int' => (int) $header['number'],
                'content_ar' => $content,
            ];
        }
    }

    private function normalizeText(string $text): string
    {
        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Strip control chars except \n \t
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        // Normalize multiple blank lines
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        // Normalize multiple spaces
        $text = preg_replace('/ {2,}/', ' ', $text);
        return trim($text);
    }

    private function matchVisualHeader(string $line): ?array
    {
        foreach ($this->visualPatterns as $key => $pattern) {
            if (preg_match($pattern, $line, $m)) {
                $type = $this->visualTypeMap[$key];
                if ($key === 'مادة_ordinal') {
                    $number = $this->visualOrdinal[trim($m[1])] ?? '1';
                } else {
                    $number = trim($m[1]);
                    $number = preg_replace('/\s*ررك\s*م/u', ' مكرر', $number);
                }
                return ['type' => $type, 'number' => $number];
            }
        }
        return null;
    }

    /** Reverses visual-order Arabic line from smalot output */
    private function fixBidiLine(string $line): string
    {
        if (trim($line) === '') return '';

        preg_match_all('/\p{Arabic}+|[^\p{Arabic}]+/u', $line, $m);
        $segments = array_reverse($m[0]);

        $segments = array_map(function (string $seg): string {
            if (preg_match('/\p{Arabic}/u', $seg)) {
                preg_match_all('/./us', $seg, $chars);
                return implode('', array_reverse($chars[0]));
            }
            return $seg;
        }, $segments);

        $result = implode('', $segments);
        $result = preg_replace('/ {2,}/', ' ', $result);
        return trim($result);
    }

    private function cleanRawText(string $text): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = preg_replace('/ {2,}/', ' ', $text);
        return trim($text);
    }
}
