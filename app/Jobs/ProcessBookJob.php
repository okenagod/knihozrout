<?php

namespace App\Jobs;

use App\Models\Book;
use App\Services\BookScanServiceInterface;
use App\Services\LibraryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessBookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * LLM na slabším stroji může běžet i minuty (+ načtení modelu do paměti).
     * Při změně hlídej DB_QUEUE_RETRY_AFTER, musí být větší.
     */
    public int $timeout = 600;

    public function __construct(
        public Book $book
    ) {}

    public function handle(BookScanServiceInterface $ocrService, LibraryService $libraryService): void
    {
        // 1. Spustíme OCR nad hlavní fotkou
        $ocrService->processBook($this->book);

        // 2. Po OCR máme (snad) ISBN, zkusíme najít metadata v knihovnách
        // Musíme znovu načíst model, aby měl v sobě nové ISBN z OCR
        $this->book->refresh();
        $libraryService->processBook($this->book);

        // 3. S názvem/ISBN dohledáme ceny v antikvariátech a obchodech (samostatný job – trvá déle a nesmí shodit OCR)
        if (config('prices.enabled'))
        {
            FetchBookPricesJob::dispatch($this->book);
        }
    }
}
