<?php

namespace App\Services\PriceServices;

use App\DTO\PriceOffer;
use App\Models\Book;
use App\Services\Llm\LlmConnector;
use App\Support\BookMatcher;
use App\Support\HtmlText;

/**
 * Obecný zdroj cen pro e-shopy bez vlastního parseru: stáhne stránku s výsledky hledání,
 * převede ji na text a LLM z ní vytáhne nabídky, které odpovídají knize.
 * Nový obchod = nový řádek v config/prices.php → llm_shops (název + URL hledání s {query}).
 *
 * Výstup LLM se ověřuje: cena musí být v textu stránky, název a autor musí odpovídat knize,
 * odkaz musí vést na stejný web – jinak se nabídka zahodí / odkaz nahradí URL hledání.
 */
class LlmShopSource extends AbstractPriceSource
{
    private const PROMPT = <<<'PROMPT'
        Níže je text stránky s výsledky vyhledávání v internetovém obchodě s knihami (odkazy jsou ve tvaru [text](url)).

        Hledaná kniha: %s

        Vrať JSON s polem "offers": nabídky z textu, které jsou TOUTO knihou – stejný název a autor (jiné vydání nebo vazba je v pořádku).
        Knihy s jiným názvem nebo jiným autorem vynech, i když jsou podobné.
        Pro každou nabídku vyplň:
        - title: název, jak je uveden na stránce,
        - author: autor, jak je uveden na stránce, jinak null,
        - price: cena, za kterou se nabídka prodává, v Kč jako číslo (ne původní ani přeškrtnutá cena),
        - condition: stav (nová / použitá / popis opotřebení), pokud je uveden, jinak null,
        - format: vazba nebo druh (pevná vazba, brožovaná, e-kniha, audiokniha…), pokud je uveden, jinak null,
        - url: odkaz na detail nabídky přesně z textu stránky, jinak null.
        Pokud žádná nabídka neodpovídá, vrať prázdné pole. Nic si nevymýšlej.

        TEXT STRÁNKY:
        %s
        PROMPT;

    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'offers' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'author' => ['type' => ['string', 'null']],
                        'price' => ['type' => 'number'],
                        'condition' => ['type' => ['string', 'null']],
                        'format' => ['type' => ['string', 'null']],
                        'url' => ['type' => ['string', 'null']],
                    ],
                    'required' => ['title', 'author', 'price', 'condition', 'format', 'url'],
                ],
            ],
        ],
        'required' => ['offers'],
    ];

    public function __construct(
        private LlmConnector $llm,
        private string $shopName,
        private string $searchUrl,
    ) {}

    public function name(): string
    {
        return $this->shopName;
    }

    public function search(Book $book): array
    {
        if (blank($book->title))
        {
            return [];
        }

        $title = BookMatcher::mainTitle($book->title);
        $url = str_replace('{query}', rawurlencode($title), $this->searchUrl);
        $text = HtmlText::fromHtml($this->get($url), $url);

        // název na stránce vůbec není → nemá smysl volat LLM (ušetří desítky sekund)
        $window = HtmlText::window($text, $title, config('prices.llm_max_chars', 24000));
        if ($window === null)
        {
            return [];
        }

        $result = $this->llm->json(
            prompt: sprintf(self::PROMPT, $this->describe($book), $window),
            schema: self::SCHEMA,
            options: ['num_ctx' => config('prices.llm_num_ctx', 16384)],
        );

        return $this->toOffers($result['offers'] ?? [], $book, $window, $url);
    }

    /**
     * Ověří výstup LLM proti stránce a knize a převede ho na PriceOffer.
     *
     * @return PriceOffer[]
     */
    public function toOffers(array $items, Book $book, string $pageText, string $searchUrl): array
    {
        $offers = [];
        $host = parse_url($searchUrl, PHP_URL_HOST);

        foreach ($items as $item)
        {
            $price = (float) ($item['price'] ?? 0);

            if ($price <= 0
                || !$this->priceInText($price, $pageText)
                || !BookMatcher::titleMatches($item['title'] ?? null, $book->title)
                || !BookMatcher::authorMatches($item['author'] ?? null, $book->author))
            {
                continue;
            }

            $url = $item['url'] ?? null;
            if (!$url || parse_url($url, PHP_URL_HOST) !== $host || !str_contains($pageText, $url))
            {
                $url = $searchUrl;
            }

            $condition = implode(', ', array_filter([$item['condition'] ?? null, $item['format'] ?? null])) ?: null;

            $offers[] = new PriceOffer($price, $this->name(), $url, $item['title'], $condition);
        }

        return $offers;
    }

    private function describe(Book $book): string
    {
        return json_encode(array_filter([
            'název' => $book->title,
            'autor' => $book->author,
            'nakladatel' => $book->publisher,
            'rok vydání' => $book->year,
        ]), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Cena se na stránce musí objevit jako číslo (s případnými mezerami v tisících a haléři).
     */
    private function priceInText(float $price, string $text): bool
    {
        $integer = (string) (int) floor($price);
        $pattern = implode('[\s\x{00A0}.]?', str_split($integer)); // "1 234" i "1234"

        return (bool) preg_match('/(?<![\d,.])' . $pattern . '(?:[,.]\d{1,2}|,-)?(?!\d)/u', $text);
    }
}
