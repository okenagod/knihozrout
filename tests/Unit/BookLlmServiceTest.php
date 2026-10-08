<?php

namespace Tests\Unit;

use App\DTO\BookScanData;
use App\Models\Book;
use App\Services\BookLlmService;
use App\Support\Isbn;
use Tests\TestCase;

class BookLlmServiceTest extends TestCase
{
    private function service(): BookLlmService
    {
        return app(BookLlmService::class);
    }

    public function test_it_normalizes_isbn_found_in_text()
    {
        $data = $this->service()->toScanData([
            'text' => "Vytiskl MÍR, Praha\nISBN 0 946352 42 9",
            'title' => 'null',
            'author' => ' Alexander Tomský ',
            'publisher' => 'Rozmluvy',
            'year' => '1977, 1987, 1990',
            'isbn' => '0 946352 42 9',
            'spn_code' => '13-030-68',
        ]);

        $this->assertSame('0-946352-42-9', $data->isbn);
        $this->assertNull($data->spnCode, 'SPN se u knihy s ISBN ignoruje');
        $this->assertSame('0-946352-42-9', $data->identifier());
        $this->assertNull($data->title);
        $this->assertSame('Alexander Tomský', $data->author);
        $this->assertSame('1990', $data->year);
    }

    public function test_it_accepts_printed_isbn_with_invalid_checksum()
    {
        $data = $this->service()->toScanData(['text' => 'ISBN 80-237-6907-0', 'isbn' => '80-237-6907-0']);

        $this->assertSame('80-237-6907-0', $data->isbn);
    }

    public function test_it_drops_identifiers_missing_in_transcribed_text()
    {
        $data = $this->service()->toScanData([
            'text' => "V roce 1985 vydalo Lidové nakladatelství\n26—041—85\nCena brož. 34,50 Kčs",
            'isbn' => '80-85946-91-2',
            'spn_code' => '13-030-68',
        ]);

        $this->assertNull($data->isbn);
        $this->assertNull($data->spnCode);
    }

    public function test_it_reads_spn_code_with_long_dashes()
    {
        $data = $this->service()->toScanData([
            'text' => "26—041—85\nCena brož. 34,50 Kčs",
            'isbn' => null,
            'spn_code' => '26—041—85',
        ]);

        $this->assertSame('26-041-85', $data->spnCode);
        $this->assertSame('26-041-85', $data->identifier());
    }

    public function test_fill_book_keeps_existing_values()
    {
        $book = new Book(['title' => 'Ručně zadaný název', 'isbn' => null]);

        $this->service()->fillBook($book, new BookScanData('Z LLM', 'Autor', 'Nakladatel', '1994', '80-237-6907-0', null));

        $this->assertSame('Ručně zadaný název', $book->title);
        $this->assertSame('Autor', $book->author);
        $this->assertSame('80-237-6907-0', $book->isbn);
        $this->assertSame('1994', $book->year);
    }

    public function test_isbn_checksum_validation()
    {
        $this->assertTrue(Isbn::isValid('0946352429'));
        $this->assertTrue(Isbn::isValid('9780306406157'));
        $this->assertFalse(Isbn::isValid('9780306406158'));
        $this->assertFalse(Isbn::isValid('8023769070'));
        $this->assertSame('80-237-6907-0', Isbn::normalize('ISBN 80-237-6907-0', strict: false));
        $this->assertNull(Isbn::normalize('ISBN 80-237-6907-0'));
        $this->assertNull(Isbn::normalize('1135', strict: false));
    }
}
