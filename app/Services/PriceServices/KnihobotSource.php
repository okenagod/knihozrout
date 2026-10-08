<?php

namespace App\Services\PriceServices;

use App\DTO\PriceOffer;
use App\Models\Book;
use App\Support\BookMatcher;
use App\Support\Isbn;

/**
 * Knihobot (knihobot.cz) – výkup a prodej použitých knih. Hledá podle ISBN i názvu.
 * Výsledky jsou v JSONu __NEXT_DATA__; u titulu je jen nejnižší cena a počet kusů skladem.
 */
class KnihobotSource extends AbstractPriceSource
{
    private const BASE_URL = 'https://knihobot.cz';

    public function name(): string
    {
        return 'Knihobot';
    }

    public function search(Book $book): array
    {
        $isbn13 = Isbn::toIsbn13($book->isbn);

        if ($isbn13)
        {
            $offers = $this->offers($this->items($isbn13), $book, $isbn13);
            if ($offers)
            {
                return $offers;
            }
        }

        if (blank($book->title))
        {
            return [];
        }

        return $this->offers($this->items(BookMatcher::mainTitle($book->title)), $book, null);
    }

    public function parseItems(string $html): array
    {
        if (!preg_match('#<script id="__NEXT_DATA__"[^>]*>(.*?)</script>#s', $html, $m))
        {
            return [];
        }

        return data_get(json_decode($m[1], true), 'props.pageProps.componentProps.items', []);
    }

    private function items(string $query): array
    {
        return $this->parseItems($this->get(self::BASE_URL . '/p/q/' . rawurlencode($query)));
    }

    /**
     * @return PriceOffer[]
     */
    private function offers(array $items, Book $book, ?string $isbn13): array
    {
        $offers = [];

        foreach ($items as $item)
        {
            $sameIsbn = $isbn13 && Isbn::toIsbn13($item['isbn'] ?? null) === $isbn13;
            $sameBook = $book->title
                && BookMatcher::titleMatches($item['grandmothers_title'] ?? null, $book->title)
                && BookMatcher::authorMatches($item['authors_name'] ?? null, $book->author);

            if (empty($item['is_in_stock']) || empty($item['highlight_price']) || !($sameIsbn || $sameBook))
            {
                continue;
            }

            $count = (int) ($item['books_count'] ?? 1);

            $offers[] = new PriceOffer(
                value: (float) $item['highlight_price'],
                source: $this->name(),
                url: self::BASE_URL . '/g/' . $item['grandmothers_id'],
                title: trim(($item['grandmothers_title'] ?? '') . ' – ' . ($item['publisher'] ?? ''), ' –'),
                condition: 'použitá' . ($count > 1 ? " (nejlevnější z {$count} ks)" : ''),
            );
        }

        return $offers;
    }
}
