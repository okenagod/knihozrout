<?php

namespace App\Services;

use App\DTO\PriceOffer;
use App\Models\Book;
use App\Services\Llm\LlmConnector;
use App\Services\PriceServices\LlmShopSource;
use App\Services\PriceServices\PriceSourceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Dohledá ceny knihy ve všech zdrojích (config/prices.php), uloží je do book_prices
 * a podle nich nastaví minPrice/maxPrice knihy.
 */
class PriceService
{
    public function __construct(
        private LlmConnector $llm,
    ) {}

    /**
     * @return PriceSourceInterface[]
     */
    public function getSources(): array
    {
        $sources = array_map(fn (string $class) => app($class), config('prices.sources', []));

        if (config('prices.llm_shops_enabled'))
        {
            foreach (config('prices.llm_shops', []) as $shop)
            {
                $sources[] = new LlmShopSource($this->llm, $shop['name'], $shop['search_url']);
            }
        }

        return $sources;
    }

    /**
     * Projde všechny zdroje. Chyba jednoho zdroje (nedostupný web, LLM) ostatní neovlivní.
     *
     * @param  callable|null $onSource fn(PriceSourceInterface $source, array $offers, ?\Throwable $error) – průběh pro CLI
     * @return PriceOffer[]
     */
    public function search(Book $book, ?callable $onSource = null): array
    {
        $offers = [];

        foreach ($this->getSources() as $source)
        {
            $error = null;
            $found = [];

            try
            {
                $found = $source->search($book);
            }
            catch (\Throwable $e)
            {
                $error = $e;
                Log::warning("Ceny: zdroj {$source->name()} selhal u knihy {$book->id}: " . $e->getMessage());
            }

            $onSource && $onSource($source, $found, $error);
            array_push($offers, ...$found);
        }

        return $this->unique($offers);
    }

    /**
     * Nahradí automaticky získané ceny knihy novými (ručně zadané zůstanou) a přepočítá min/max.
     *
     * @return PriceOffer[]
     */
    public function updateBook(Book $book, ?callable $onSource = null): array
    {
        $offers = $this->search($book, $onSource);

        DB::transaction(function () use ($book, $offers) {
            $book->prices()->where('is_manual', false)->delete();

            foreach ($offers as $offer)
            {
                $book->prices()->create($offer->toArray());
            }
        });

        $book->refreshPriceRange();

        return $offers;
    }

    /**
     * Stejná nabídka může přijít víckrát (např. stejné vydání nalezené přes ISBN i název).
     *
     * @param  PriceOffer[] $offers
     * @return PriceOffer[]
     */
    private function unique(array $offers): array
    {
        return collect($offers)
            ->unique(fn (PriceOffer $offer) => $offer->source . '|' . $offer->url . '|' . $offer->value . '|' . $offer->condition)
            ->values()
            ->all();
    }
}
