<?php

namespace App\Jobs;

use App\Models\Book;
use App\Services\PriceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchBookPricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Několik webů + LLM extrakce (desítky sekund na obchod). Hlídej DB_QUEUE_RETRY_AFTER.
     */
    public int $timeout = 600;

    // chyby jednotlivých zdrojů se logují uvnitř PriceService, opakování by jen znovu stahovalo weby
    public int $tries = 1;

    public function __construct(
        public Book $book
    ) {}

    public function handle(PriceService $priceService): void
    {
        $priceService->updateBook($this->book);
    }
}
