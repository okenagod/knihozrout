<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Services\BookOcrService;
use Illuminate\Console\Command;

/**
 * Class RunOcr
 *
 * Command to process books requiring OCR (Optical Character Recognition).
 *
 * Handles the identification and processing of books by using the BookOcrService.
 * Books are retrieved based on parameters such as ID or incomplete OCR data,
 * with results or exceptions being logged during processing.
 */
class RunOcr extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:run-ocr {id?}';

    /**
     * @var string
     */
    protected $description = 'Spustí OCR u knih, které ještě nebyly zpracovány.';

    public function __construct(
        private BookOcrService $service
    ) {
        parent::__construct();
    }

    /**
     * Processes books by detecting and handling books that require OCR processing.
     *
     * Retrieves books based on the specified ID or identifies books needing OCR processing
     * by checking for missing or empty `ocr_full_text` fields. For each book, the OCR service
     * is executed, and relevant information or errors are logged accordingly.
     *
     * The method performs the following actions:
     * - Searches for specific books by ID or books with incomplete OCR data.
     * - Logs the number of books to process or skips processing if none are found.
     * - Iterates through each book, processes the OCR, and logs results or errors.
     *
     * @return void
     */
    public function handle()
    {
        $id = $this->argument('id');

        if ($id)
        {
            $books = Book::where('id', $id)->get();
        }
        else
        {
            // Hlavní podmínka: hledáme knihy, kde je ocr_full_text prázdný nebo null
            $books = Book::query()
                ->where('status', '!=', 'done')
                ->where(function ($query) {
                    $query->whereNull('ocr_full_text')
                        ->orWhere('ocr_full_text', '');
                })
                ->get();
        }

        if ($books->isEmpty())
        {
            $this->info('Žádné knihy ke zpracování.');

            return;
        }

        $this->info('Nalezeno knih ke zpracování: ' . $books->count());

        foreach ($books as $book)
        {
            $this->info("Zpracovávám knihu ID {$book->id} (Bin: {$book->bin_number})...");

            try
            {
                $this->service->processBook($book);
                $this->info("Kniha {$book->id} zpracována. Nalezený text délky: " . strlen($book->ocr_full_text));
                if ($book->isbn)
                {
                    $this->line("Identifikováno ISBN/SPN: <fg=green>{$book->isbn}</>");
                }
            }
            catch (\Exception $e)
            {
                $this->error("Chyba u knihy {$book->id}: " . $e->getMessage());
            }

            $this->line('-----------------------------------');
        }

        $this->info('Hotovo.');
    }
}
