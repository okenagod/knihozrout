<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Services\BookOcrService;
use Illuminate\Console\Command;

/**
 * Class RunRegex
 *
 * Represents a console command that processes books using OCR and regex to identify ISBN/SPN data.
 * Filters books that have non-empty OCR full text and are not marked as 'done'.
 */
class RunRegex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:run-regex';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Spusti REGEX z OCR u knih, kde neni nactene ISBN/SPN';

    /**
     * Handles the processing of books that meet specific criteria.
     *
     * This method retrieves books where the `ocr_full_text` is not null and the
     * `status` is not set to 'done'. It processes each retrieved book, updates
     * regex-formatted text, and displays book details, including whether an
     * ISBN/SPN was identified. If no books meet the criteria, it outputs an
     * appropriate message.
     */
    public function handle(BookOcrService $service)
    {
        // Hlavní podmínka: hledáme knihy, kde je ocr_full_text prázdný nebo null
        $books = Book::query()
            ->whereNotNull('ocr_full_text')
            ->where('status', '!=', 'done')
            ->get();

        if ($books->isEmpty())
        {
            $this->info('Žádné knihy ke zpracování.');

            return;
        }

        $this->info('Nalezeno knih ke zpracování: ' . $books->count());

        foreach ($books as $book)
        {
            $this->info("Zpracovávám knihu ID {$book->id} (Bin: {$book->bin_number})...");

            $service->updateRegexText($book);
            $book->save();
            $this->line("<fg=green>Kniha {$book->id} zpracována.</>");
            if ($book->isbn)
            {
                $this->line("Identifikováno ISBN/SPN: <fg=green>{$book->isbn}</>");
            }
            else
            {
                $this->error('ISBN/SPN: nenalezeno');
            }

            $this->line('-----------------------------------');
        }

        $this->info('Hotovo.');
    }
}
