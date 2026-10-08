<?php

namespace App\Services\PriceServices;

use App\DTO\PriceOffer;
use App\Models\Book;
use App\Support\BookMatcher;
use Illuminate\Support\Str;

/**
 * Trh knih (trhknih.cz) – tržiště antikvariátů i soukromých prodejců.
 * Hledá jen podle názvu (ISBN nepodporuje). Výsledek hledání = jedno vydání, na jeho detailu
 * jsou jednotlivé nabídky prodejců s cenou a popisem stavu.
 */
class TrhKnihSource extends AbstractPriceSource
{
    private const BASE_URL = 'https://www.trhknih.cz';

    public function name(): string
    {
        return 'Trh knih';
    }

    public function search(Book $book): array
    {
        if (blank($book->title))
        {
            return [];
        }

        $html = $this->get(self::BASE_URL . '/hledat', ['q' => BookMatcher::mainTitle($book->title)]);

        $offers = [];
        foreach ($this->selectIssues($this->parseSearch($html), $book) as $issue)
        {
            $detail = $this->get(self::BASE_URL . $issue['path']);
            array_push($offers, ...$this->parseOffers($detail, self::BASE_URL . $issue['path'], $issue['title']));
        }

        return $offers;
    }

    /**
     * Vydání z výsledků hledání: path, title, author, year, publisher, hasOffers.
     */
    public function parseSearch(string $html): array
    {
        $issues = [];

        foreach (array_slice(explode('class="row serp-item"', $html), 1) as $block)
        {
            if (!preg_match('#<p>(.*?)</p>#s', $block, $p) || !preg_match('#<a href="(/kniha/[^"]+)">([^<]+)</a>#', $p[1], $link))
            {
                continue;
            }

            preg_match('#<em>([^<]*)</em>\s*,\s*<em>([^<]*)</em>#', $p[1], $em);

            // řádky: název, [podtitul], [cena], autor, <em>rok</em>, <em>nakladatel</em>
            $lines = array_values(array_filter(array_map(
                fn ($line) => trim(html_entity_decode(strip_tags($line))),
                preg_split('#<br\s*/?>#', preg_replace('#<em>.*$#s', '', $p[1]))
            )));

            $issues[] = [
                'path' => $link[1],
                'title' => trim(html_entity_decode($link[2])),
                'author' => count($lines) > 1 ? end($lines) : null,
                'year' => $em[1] ?? null,
                'publisher' => isset($em[2]) ? html_entity_decode($em[2]) : null,
                'hasOffers' => str_contains($p[1], 'ask-count'),
            ];
        }

        return $issues;
    }

    /**
     * Vydání odpovídající knize a s nabídkami. Když sedí i rok vydání, bereme jen ta.
     */
    public function selectIssues(array $issues, Book $book): array
    {
        $matching = array_filter($issues, fn (array $issue) => $issue['hasOffers']
            && BookMatcher::titleMatches($issue['title'], $book->title)
            && BookMatcher::authorMatches($issue['author'], $book->author));

        if ($book->year)
        {
            $sameYear = array_filter($matching, fn (array $issue) => $issue['year'] === (string) $book->year);
            $matching = $sameYear ?: $matching;
        }

        return array_slice(array_values($matching), 0, config('prices.trhknih_max_issues', 3));
    }

    /**
     * Nabídky prodejců na detailu vydání.
     *
     * @return PriceOffer[]
     */
    public function parseOffers(string $html, string $url, ?string $title = null): array
    {
        $offers = [];

        preg_match_all('#data-ask-id="(\d+)"\s+data-ask-price="([\d.,]+)"#', $html, $asks, PREG_SET_ORDER);

        foreach ($asks as [, $askId, $price])
        {
            $condition = null;
            if (preg_match('#id="ask-details-' . $askId . '".*?<div class="desc">(.*?)</div>#s', $html, $desc))
            {
                $condition = $this->parseCondition($desc[1]);
            }

            $value = $this->parsePrice($price);
            if ($value)
            {
                $offers[] = new PriceOffer($value, $this->name(), $url, $title, $condition);
            }
        }

        return $offers;
    }

    /**
     * Popis nabídky začíná strukturovaným "stav: …", často jen "stav: viz dále" a skutečný stav je v textu
     * inzerátu. Bereme první smysluplné "Stav: …", jinak zkrácený text popisu.
     */
    private function parseCondition(string $descHtml): ?string
    {
        $paragraphs = array_values(array_filter(array_map(
            fn ($p) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($p)))),
            preg_split('#</p>#', $descHtml)
        )));

        preg_match_all('/stav:\s*([^.\n]+)/iu', implode("\n", $paragraphs), $states);

        foreach ($states[1] as $state)
        {
            if (!preg_match('/^viz\b/iu', trim($state)))
            {
                return trim($state);
            }
        }

        // jen "stav: viz dále" → použijeme zbytek popisu
        $rest = trim(implode(' ', array_filter($paragraphs, fn ($p) => !preg_match('/^stav:\s*viz\b/iu', $p))));

        return $rest === '' ? null : Str::limit($rest, 150);
    }
}
