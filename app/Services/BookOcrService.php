<?php

namespace App\Services;

use App\Models\Book;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;
use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Feature\Type;
use Google\Cloud\Vision\V1\Image;

/**
 * The BookOcrService class provides methods for processing books through OCR (Optical Character Recognition),
 * extracting metadata such as ISBN, title, or author, and handling integration with external APIs like
 * Google Vision and OpenLibrary.
 */
class BookOcrService implements BookScanServiceInterface
{
    public function __construct(
        private ImageAnnotatorClient $client,
        private ImageProcessingService $imageProcessing,
    ) {}

    public function __destruct()
    {
        $this->client->close();
    }

    public function processBook(Book $book): void
    {
        // Kontrola, zda kniha vůbec má fotky
        if (!$book->main_photo)
        {
            $book->ocr_full_text = 'Žádné fotky k analýze.';
            $book->save();

            return;
        }

        $fullText = '';
        $imageRequests = [];

        // Zkusíme najít originál pro lepší OCR
        $path = $this->imageProcessing->getOriginalPath($book->main_photo);
        $content = file_get_contents($path);

        $image = (new Image)->setContent($content);
        $feature = (new Feature)->setType(Type::TEXT_DETECTION);

        // Jednotlivý požadavek na jednu fotku
        $imageRequests[] = (new AnnotateImageRequest)
            ->setImage($image)
            ->setFeatures([$feature]);

        if (!count($imageRequests))
        {
            return;
        }

        // Obalení do Batch požadavku
        $batchRequest = (new BatchAnnotateImagesRequest)
            ->setRequests($imageRequests);

        // Odeslání
        $response = $this->client->batchAnnotateImages($batchRequest);
        $responses = $response->getResponses();

        foreach ($responses as $res)
        {
            if ($res->hasError())
            {
                \Log::error('Google OCR Error: ' . $res->getError()->getMessage());

                continue;
            }

            $annotation = $res->getFullTextAnnotation();
            if ($annotation)
            {
                $fullText .= $annotation->getText() . "\n";
            }
        }

        // Uložení a analýza
        $book->ocr_full_text = $fullText;

        $this->updateRegexText($book);

        $book->status = 'review';
        $book->save();
    }

    /**
     * Aktualizuje text pomocí regulárních výrazů pro poskytnutou knihu.
     * Pokud kniha nemá nastaveno ISBN, pokusí se jej určit pomocí metody getRegexIsbn.
     * Pokud kniha nemá nastavený název a je označena jako starožitná,
     * pokusí se odvodit název z jejího textu pomocí metody getTitle.
     *
     * @param Book $book Kniha, která má být aktualizována.
     */
    public function updateRegexText(Book $book): void
    {
        if (!$book->isbn)
        {
            $book->isbn = $this->getRegexIsbn($book);
        }

        if (!$book->title && $book->is_antique)
        {
            $book->title = $this->getTitle($book);
        }
    }

    /**
     * Load first line from OCR text as title
     */
    protected function getTitle(Book $book)
    {
        $lines = collect(explode("\n", $book->ocr_full_text))->map(fn ($l) => trim($l))->filter();

        return $lines->first();
    }

    /**
     * Extracts an ISBN or alternative identifier from the book's OCR full text.
     *
     * This method attempts to locate and validate a standard ISBN-10 or ISBN-13 format
     * within the full text of the book. If none is found and the book is marked as antique,
     * it further searches for a custom SPN-like code.
     *
     * @param  Book        $book The book entity containing OCR full text and antique status.
     * @return string|null Returns a validated ISBN or custom identifier, or null if none found.
     */
    protected function getRegexIsbn(Book $book): ?string
    {
        $fullText = $book->ocr_full_text;

        // 1. Hledáme cokoli, co vypadá jako ISBN (10 nebo 13 číslic, i s pomlčkami)
        if (preg_match('/(?:ISBN(?:-1[03])?:?\s*)?([0-9Xx\s-]{10,20})/i', $fullText, $matches))
        {
            $cleanIsbn = preg_replace('/[^0-9Xx\-]/', '', $matches[1]);

            // Validace: ISBN má mít 10 nebo 13 znaků
            if (strlen($cleanIsbn) === 10 || strlen($cleanIsbn) === 13)
            {
                return $cleanIsbn;
            }
        }

        // 2. Pokud je to antikvární (is_antique), zkusíme najít ten tvůj SPN kód (např. 14-45-76)
        if ($book->is_antique && preg_match('/(\d{2}-\d{2,3}-\d{2})/i', $fullText, $matches))
        {
            return $matches[1]; // Pro staré knihy uložíme kód jako identifikátor
        }

        return null;
    }
}
