<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;

class FixArticleNumberInt extends Command
{
    protected $signature   = 'articles:fix-number-int';
    protected $description = 'Recompute number_int from the number field (extracts leading integer only)';

    public function handle(): int
    {
        $total   = Article::count();
        $fixed   = 0;
        $bar     = $this->output->createProgressBar($total);

        Article::select('id', 'number', 'number_int')->chunkById(500, function ($chunk) use (&$fixed, $bar) {
            foreach ($chunk as $article) {
                $correct = (int) $article->number;
                if ($article->number_int !== $correct) {
                    $article->updateQuietly(['number_int' => $correct]);
                    $fixed++;
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Done — fixed {$fixed} / {$total} articles.");

        return self::SUCCESS;
    }
}
