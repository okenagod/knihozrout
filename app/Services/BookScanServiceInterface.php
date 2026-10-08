<?php

namespace App\Services;

use App\Models\Book;

/**
 * Přečte tiráž knihy (main_photo), doplní nalezené údaje, uloží text do ocr_full_text
 * a přepne knihu do stavu 'review'.
 *
 * Implementace se volí v config/services.php → book_scan.driver (BOOK_SCAN_DRIVER):
 *  - google → BookOcrService (Google Vision OCR + regex)
 *  - llm    → BookLlmService (vision LLM vrací strukturovaný JSON)
 */
interface BookScanServiceInterface
{
    public function processBook(Book $book): void;
}
