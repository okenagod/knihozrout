<?php

namespace App\DTO;

/**
 * Údaje o knize přečtené z fotky tiráže (výstup BookLlmService).
 */
class BookScanData
{
    public function __construct(
        public readonly ?string $title,
        public readonly ?string $author,
        public readonly ?string $publisher,
        public readonly ?string $year,
        public readonly ?string $isbn,
        public readonly ?string $spnCode,
        public readonly string $text = '',
    ) {}

    /**
     * Identifikátor ukládaný do sloupce books.isbn – ISBN, u starých knih SPN kód.
     */
    public function identifier(): ?string
    {
        return $this->isbn ?? $this->spnCode;
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'author' => $this->author,
            'publisher' => $this->publisher,
            'year' => $this->year,
            'isbn' => $this->isbn,
            'spn_code' => $this->spnCode,
        ];
    }
}
