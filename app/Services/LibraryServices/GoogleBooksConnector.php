<?php

namespace App\Services\LibraryServices;

use App\DTO\BookData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleBooksConnector implements LibraryConnectorInterface
{
    public function fetch(string $isbn): ?BookData
    {
        $isbn = preg_replace('/[^0-9Xx]/', '', $isbn);
        $apiKey = config('services.google.books_api_key');

        try
        {
            $response = Http::get('https://www.googleapis.com/books/v1/volumes', [
                'q' => "isbn:{$isbn}",
                'key' => $apiKey,
            ]);

            if (! $response->successful())
            {
                return null;
            }

            $data = $response->json();

            if (($data['totalItems'] ?? 0) === 0)
            {
                return null;
            }

            $volume = $data['items'][0]['volumeInfo'];

            // Extrakce roku
            $year = null;
            if (isset($volume['publishedDate']) && preg_match('/\d{4}/', $volume['publishedDate'], $matches))
            {
                $year = $matches[0];
            }

            return new BookData(
                title: $volume['title'] ?? null,
                author: isset($volume['authors']) ? implode(', ', $volume['authors']) : null,
                publisher: $volume['publisher'] ?? null,
                year: $year,
            );
        }
        catch (\Exception $e)
        {
            Log::error('Google Books Error: ' . $e->getMessage());

            return null;
        }
    }
}
