<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Services\BookLlmService;
use App\Services\ImageProcessingService;
use App\Services\Llm\LlmConnector;
use Illuminate\Console\Command;

/**
 * Otestuje čtení tiráže přes LLM nad knihami v DB a porovná výsledek s uloženými (zkontrolovanými) údaji.
 * Bez --save nic neukládá, takže je bezpečné ho pouštět i nad knihami ve stavu 'done'.
 */
class LlmScan extends Command
{
    protected $signature = 'app:llm-scan {id?* : ID knih, výchozí všechny s hlavní fotkou} {--save : Výsledek uložit stejně jako ProcessBookJob (jen BookLlmService, bez knihoven)}';

    protected $description = 'Přečte tiráž knih přes LLM a porovná výsledek s údaji v DB.';

    private const FIELDS = ['isbn', 'title', 'author', 'publisher', 'year'];

    public function handle(BookLlmService $service, LlmConnector $llm, ImageProcessingService $images): int
    {
        if (!$llm->isAvailable())
        {
            $this->error('LLM server není dostupný (' . config('services.llm.base_url') . ').');

            return self::FAILURE;
        }

        $books = Book::query()
            ->whereNotNull('main_photo')
            ->when($this->argument('id'), fn ($q, $ids) => $q->whereIn('id', $ids))
            ->orderBy('id')
            ->get();

        $matches = 0;
        $compared = 0;

        foreach ($books as $book)
        {
            $this->line("<options=bold>Kniha {$book->id}</> ({$book->main_photo})");

            try
            {
                if ($this->option('save'))
                {
                    $start = microtime(true);
                    $service->processBook($book);
                    $this->info(sprintf('Uloženo (%.1f s): %s', microtime(true) - $start, $book->ocr_full_text));

                    continue;
                }

                $start = microtime(true);
                $data = $service->scanImage($images->getOriginalPath($book->main_photo));
                $elapsed = microtime(true) - $start;
            }
            catch (\Exception $e)
            {
                $this->error('Chyba: ' . $e->getMessage());

                continue;
            }

            $llmValues = [
                'isbn' => $data->identifier(),
                'title' => $data->title,
                'author' => $data->author,
                'publisher' => $data->publisher,
                'year' => $data->year,
            ];

            $rows = [];
            foreach (self::FIELDS as $field)
            {
                $dbValue = $book->{$field};
                $same = $this->normalize($dbValue) === $this->normalize($llmValues[$field]);

                if ($dbValue)
                {
                    $compared++;
                    $matches += $same ? 1 : 0;
                }

                $rows[] = [$field, $dbValue, $llmValues[$field], $dbValue ? ($same ? '✔' : '✘') : '–'];
            }

            $this->table(['Pole', 'DB', 'LLM', ''], $rows);
            $this->line(sprintf('Čas: %.1f s', $elapsed));
        }

        if ($compared)
        {
            $this->info("Shoda s DB: {$matches}/{$compared} vyplněných polí.");
        }

        return self::SUCCESS;
    }

    /**
     * Porovnání bez ohledu na velikost písmen, mezery a pomlčky v ISBN.
     */
    private function normalize(?string $value): string
    {
        return mb_strtolower(preg_replace('/[\s\-.,]+/u', '', (string) $value));
    }
}
