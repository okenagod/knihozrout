<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Services\PriceService;
use App\Services\PriceServices\PriceSourceInterface;
use Illuminate\Console\Command;

/**
 * Dohledá ceny knih ve všech zdrojích. Bez --save jen vypíše, co našel.
 */
class FetchPrices extends Command
{
    protected $signature = 'app:fetch-prices {id?* : ID knih, výchozí všechny bez cen} {--save : Ceny uložit a přepočítat min/max}';

    protected $description = 'Dohledá ceny knih v antikvariátech a obchodech.';

    public function handle(PriceService $service): int
    {
        $books = Book::query()
            ->when(
                $this->argument('id'),
                fn ($q, $ids) => $q->whereIn('id', $ids),
                fn ($q) => $q->whereNotNull('title')->whereDoesntHave('prices')
            )
            ->orderBy('id')
            ->get();

        foreach ($books as $book)
        {
            $this->line("<options=bold>Kniha {$book->id}</>: {$book->title} – {$book->author} ({$book->year}, ISBN {$book->isbn})");

            $progress = function (PriceSourceInterface $source, array $offers, ?\Throwable $error) {
                $error
                    ? $this->error("  {$source->name()}: " . $error->getMessage())
                    : $this->line("  {$source->name()}: " . count($offers) . ' nabídek');
            };

            $offers = $this->option('save')
                ? $service->updateBook($book, $progress)
                : $service->search($book, $progress);

            $this->table(
                ['Zdroj', 'Cena', 'Název', 'Stav', 'URL'],
                array_map(fn ($o) => [$o->source, $o->value . ' ' . $o->currency, $o->title, $o->condition, $o->url], $offers)
            );

            if ($this->option('save'))
            {
                $book->refresh();
                $this->info("Uloženo, min {$book->minPrice} / max {$book->maxPrice} Kč");
            }
        }

        return self::SUCCESS;
    }
}
