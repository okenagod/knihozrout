<?php

namespace Tests\Unit\LibraryServices;

use App\Services\LibraryServices\GoogleBooksConnector;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleBooksConnectorTest extends TestCase
{
    public function test_it_can_fetch_book_data_from_google_books()
    {
        $isbn = '1234567890';
        $jsonResponse = [
            'totalItems' => 1,
            'items' => [
                [
                    'volumeInfo' => [
                        'title' => 'Google Book',
                        'authors' => ['Jane Smith'],
                        'publisher' => 'Google Publishing',
                        'publishedDate' => '2022-01-01',
                    ]
                ]
            ]
        ];

        Http::fake([
            'www.googleapis.com/*' => Http::response($jsonResponse, 200),
        ]);

        $connector = new GoogleBooksConnector();
        $result = $connector->fetch($isbn);

        $this->assertNotNull($result);
        $this->assertEquals('Google Book', $result->title);
        $this->assertEquals('Jane Smith', $result->author);
        $this->assertEquals('Google Publishing', $result->publisher);
        $this->assertEquals(2022, $result->year);
    }

    public function test_it_returns_null_when_google_books_finds_nothing()
    {
        $isbn = '9999999999';
        $jsonResponse = [
            'totalItems' => 0
        ];

        Http::fake([
            'www.googleapis.com/*' => Http::response($jsonResponse, 200),
        ]);

        $connector = new GoogleBooksConnector();
        $result = $connector->fetch($isbn);

        $this->assertNull($result);
    }
}
