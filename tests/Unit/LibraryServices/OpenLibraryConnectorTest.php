<?php

namespace Tests\Unit\LibraryServices;

use App\Services\LibraryServices\OpenLibraryConnector;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenLibraryConnectorTest extends TestCase
{
    public function test_it_can_fetch_book_data_from_open_library()
    {
        $isbn = '1234567890';
        $jsonResponse = [
            "ISBN:{$isbn}" => [
                'title' => 'Test Book',
                'authors' => [['name' => 'John Doe']],
                'publishers' => [['name' => 'Test Publisher']],
                'publish_date' => '2023',
            ]
        ];

        Http::fake([
            'openlibrary.org/*' => Http::response($jsonResponse, 200),
        ]);

        $connector = new OpenLibraryConnector();
        $result = $connector->fetch($isbn);

        $this->assertNotNull($result);
        $this->assertEquals('Test Book', $result->title);
        $this->assertEquals('John Doe', $result->author);
        $this->assertEquals('Test Publisher', $result->publisher);
        $this->assertEquals('2023', $result->year);
    }

    public function test_it_returns_null_when_book_not_found()
    {
        $isbn = 'nonexistent';
        Http::fake([
            'openlibrary.org/*' => Http::response([], 200),
        ]);

        $connector = new OpenLibraryConnector();
        $result = $connector->fetch($isbn);

        $this->assertNull($result);
    }
}
