<?php

namespace App\Services\LibraryServices;

use App\DTO\BookData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenLibraryConnector implements LibraryConnectorInterface
{
    public function fetch(string $isbn): ?BookData
    {
        $isbn = preg_replace('/[^0-9Xx]/', '', $isbn);
        $url = 'https://openlibrary.org/api/books';

        try
        {
            $response = Http::get($url, [
                'bibkeys' => "ISBN:{$isbn}",
                'format' => 'json',
                'jscmd' => 'data',
            ]);

            if (! $response->successful())
            {
                return null;
            }

            $data = $response->json();
            $key = "ISBN:{$isbn}";

            if (empty($data[$key]))
            {
                return null;
            }

            $bookData = $data[$key];

            // Extrakce roku
            $year = null;
            if (isset($bookData['publish_date']) && preg_match('/\d{4}/', $bookData['publish_date'], $matches))
            {
                $year = $matches[0];
            }

            return new BookData(
                $bookData['title'] ?? null,
                isset($bookData['authors']) ? collect($bookData['authors'])->pluck('name')->implode(', ') : null,
                isset($bookData['publishers']) ? collect($bookData['publishers'])->pluck('name')->implode(', ') : null,
                $year
            );
        }
        catch (\Exception $e)
        {
            Log::error('OpenLibrary Error: ' . $e->getMessage());

            return null;
        }
    }
}
