<?php

namespace App\Services;

use App\DTO\BookScanData;
use App\Models\Book;
use App\Services\Llm\LlmConnector;
use App\Support\Isbn;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;

/**
 * Náhrada za BookOcrService: fotku tiráže pošle vision LLM (Ollama, výchozí qwen2.5vl:7b),
 * který rovnou vrátí přepis textu i strukturované údaje o knize – odpadá parsování OCR textu regexy.
 *
 * Model nejdřív doslova přepíše text (pole "text" je ve schématu první) a teprve z něj vyplní údaje –
 * výrazně to omezuje vymýšlení. ISBN a SPN navíc ověřujeme proti přepisu.
 */
class BookLlmService implements BookScanServiceInterface
{
    // Pozor: v promptu nedávat konkrétní příklady kódů – malý model je pak opisuje do výsledku.
    private const PROMPT = <<<'PROMPT'
        Na fotografii je část knihy – obvykle tiráž (impresum), ale může to být i zadní strana obálky jen s ISBN nebo čárovým kódem, titulní list či obálka. Kniha je obvykle česká nebo slovenská. Vrať JSON.

        Postup:
        1. Do pole "text" nejdřív doslova přepiš veškerý tištěný text z fotografie (řádky odděl \n). Ručně psané poznámky (např. inventární čísla) vynech.
        2. Pak z přepsaného textu vyplň ostatní pole. Údaj, který v přepsaném textu není, vrať jako null – nic nedoplňuj z vlastních znalostí.

        Pole:
        - title: název knihy. Název nakladatelství, edice, tiskárny nebo série NENÍ název knihy.
        - author: autor/autoři ve tvaru "Jméno Příjmení", více autorů odděl čárkou. Překladatele, ilustrátory, redaktory ani autora obálky neuváděj.
        - publisher: nakladatelství, které vydalo TOTO vydání (ne tiskárna, ne nakladatel originálu nebo předchozího vydání).
        - year: rok TOHOTO vydání jako 4 číslice (ne rok vydání originálu).
        - isbn: ISBN přesně tak, jak je vytištěno, i když je na fotce jen ISBN bez dalšího textu. Pokud ISBN na fotce není, null.
        - spn_code: jen u starých knih bez ISBN – kód ze tří skupin číslic oddělených pomlčkami (2 číslice, 2–3 číslice, 2 číslice; poslední skupina je rok vydání), bývá na konci tiráže u ceny v Kčs. Opiš ho přesně z přepsaného textu. Jinak null.
        PROMPT;

    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'text' => ['type' => 'string'],
            'title' => ['type' => ['string', 'null']],
            'author' => ['type' => ['string', 'null']],
            'publisher' => ['type' => ['string', 'null']],
            'year' => ['type' => ['string', 'null']],
            'isbn' => ['type' => ['string', 'null']],
            'spn_code' => ['type' => ['string', 'null']],
        ],
        'required' => ['text', 'title', 'author', 'publisher', 'year', 'isbn', 'spn_code'],
    ];

    public function __construct(
        private LlmConnector $llm,
        private ImageManager $imageManager,
        private ImageProcessingService $imageProcessing,
    ) {}

    public function processBook(Book $book): void
    {
        if (!$book->main_photo)
        {
            $book->ocr_full_text = 'Žádné fotky k analýze.';
            $book->save();

            return;
        }

        $raw = $this->readImage($this->imageProcessing->getOriginalPath($book->main_photo));
        $data = $this->toScanData($raw);

        // přepis textu nahrazuje OCR výstup; pod něj přidáme, co model vytěžil (pro kontrolu adminem)
        $extracted = array_diff_key($raw, ['text' => true]);
        $book->ocr_full_text = $data->text . "\n\n--- LLM ---\n"
            . json_encode($extracted, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->fillBook($book, $data);

        $book->status = 'review';
        $book->save();
    }

    /**
     * Přečte tiráž z obrázku (absolutní cesta) a vrátí normalizovaná data.
     */
    public function scanImage(string $path): BookScanData
    {
        return $this->toScanData($this->readImage($path));
    }

    /**
     * Doplní do knihy jen prázdné údaje – co admin vyplnil ručně, nepřepisujeme.
     */
    public function fillBook(Book $book, BookScanData $data): void
    {
        $book->isbn = $book->isbn ?: $data->identifier();
        $book->title = $book->title ?: $data->title;
        $book->author = $book->author ?: $data->author;
        $book->publisher = $book->publisher ?: $data->publisher;
        $book->year = $book->year ?: $data->year;
    }

    /**
     * Převede surový JSON z modelu na BookScanData – ověří ISBN (kontrolní číslice), SPN a rok
     * a zahodí identifikátory, které v přepsaném textu vůbec nejsou (model si je vymyslel).
     */
    public function toScanData(array $raw): BookScanData
    {
        $text = trim((string) ($raw['text'] ?? ''));

        // kontrolní číslici nevyžadujeme (viz Isbn::normalize), proti vymýšlení chrání kontrola v přepisu
        $isbn = Isbn::normalize($raw['isbn'] ?? null, strict: false);
        if ($isbn && !$this->textContains($text, $isbn))
        {
            $isbn = null;
        }

        if (!$isbn && !empty($raw['isbn']))
        {
            Log::warning('LLM vrátilo neplatné nebo nepřečtené ISBN: ' . $raw['isbn']);
        }
        elseif ($isbn && !Isbn::isValid(preg_replace('/[^0-9X]/', '', $isbn)))
        {
            Log::info("ISBN {$isbn} má neplatnou kontrolní číslici (může být chyba tisku i čtení).");
        }

        // SPN má smysl jen u knih bez ISBN
        $spn = $isbn ? null : Isbn::normalizeSpn($raw['spn_code'] ?? null);
        if ($spn && !$this->textContains($text, $spn))
        {
            Log::warning("LLM vrátilo SPN {$spn}, který v přepsaném textu není.");
            $spn = null;
        }

        return new BookScanData(
            title: $this->clean($raw['title'] ?? null),
            author: $this->clean($raw['author'] ?? null),
            publisher: $this->clean($raw['publisher'] ?? null),
            year: $this->normalizeYear($raw['year'] ?? null),
            isbn: $isbn,
            spnCode: $spn,
            text: $text,
        );
    }

    /**
     * Pošle obrázek do LLM a vrátí surový (nenormalizovaný) JSON.
     */
    private function readImage(string $path): array
    {
        return $this->llm->json(
            prompt: self::PROMPT,
            schema: self::SCHEMA,
            images: [$this->prepareImage($path)],
            model: config('services.llm.vision_model'),
        );
    }

    /**
     * Zmenší fotku – originál z mobilu (např. 2016×4032) zpracovává vision model několik minut,
     * při 1280 px je to řádově 15 s bez znatelné ztráty přesnosti.
     */
    private function prepareImage(string $path): string
    {
        $size = config('services.book_scan.llm_image_size', 1280);

        $image = $this->imageManager->read($path);
        $image->scaleDown($size, $size);

        return (string) $image->toJpeg(quality: 85);
    }

    /**
     * Obsahuje text danou sekvenci číslic? Ignoruje mezery, pomlčky a jiné oddělovače.
     */
    private function textContains(string $text, string $code): bool
    {
        $digits = fn (string $value) => preg_replace('/[^0-9X]/', '', strtoupper($value));

        return $digits($code) !== '' && str_contains($digits($text), $digits($code));
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return in_array(mb_strtolower($value), ['', 'null', 'n/a', 'neuvedeno'], true) ? null : $value;
    }

    private function normalizeYear(?string $year): ?string
    {
        if ($year && preg_match_all('/\b(1[4-9]\d{2}|20\d{2})\b/', $year, $matches))
        {
            // při více letech ("1977, 1987, 1990") bereme poslední = aktuální vydání
            return end($matches[1]);
        }

        return null;
    }
}
