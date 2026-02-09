<?php

namespace App\Services\LibraryServices;

use App\DTO\BookData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KnihovnyCzConnector implements LibraryConnectorInterface
{
    protected string $baseUrl = 'https://www.knihovny.cz/api/v1';

    public function fetch(string $isbn): ?BookData
    {
        $url = $this->baseUrl . '/search';
        $queryParams = [
            'lookfor' => 'isbn:' . $isbn,
            'type' => 'AllFields',
            'field' => ['title', 'authors', 'publicationDates', 'publishers', /*'fullRecord', 'rawData'*/],
            'sort' => 'relevance',
            'page' => 1,
            'limit' => 20,
            'prettyPrint' => 'false',
            'lng' => 'cs',
        ];

        try
        {
            $response = Http::get($url, $queryParams);

            if ($response->failed())
            {
                Log::error('Knihovna.cz API request failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();

            if (empty($data['records']))
            {
                return null;
            }

            $record = $data['records'][0];

            // Extrahování a čištění dat
            $title = $record['title'] ?? null;
            $author = $this->extractAuthor($record['authors'] ?? []);
            $year = $record['publicationDates'][0] ?? null;
            $publisher = $this->cleanPublisher($record['publishers'][0] ?? null);

            if (!$title || !$author)
            {
                return null;
            }

            return new BookData($title, $author, $publisher, $year);
        }
        catch (\Exception $e)
        {
            Log::error('Error processing Knihovna.cz API response', [
                'message' => $e->getMessage(),
                'isbn' => $isbn,
                'response_body' => $response->body() ?? 'N/A',
            ]);
            return null;
        }
    }

    /**
     * Extrahuje prvního autora z komplexní struktury.
     */
    private function extractAuthor(array $authors): ?string
    {
        $authorName = null;

        if (!empty($authors['primary']))
        {
            // Klíčem je jméno autora
            $authorName = array_key_first($authors['primary']);
        }
        elseif (!empty($authors['secondary']))
        {
            $authorName = array_key_first($authors['secondary']);
        }
        elseif (!empty($authors['corporate']))
        {
            $authorName = array_key_first($authors['corporate']);
        }

        return trim(preg_replace('/, \d{4}-?.*/', '', $authorName));
    }

    /**
     * Získá název vydavatele z řetězce.
     * Příklad: "Praha : Academia, 2011" -> "Academia"
     * Příklad: "FIN PUBLISHING," -> "FIN PUBLISHING"
     */
    private function cleanPublisher(?string $publisherString): ?string
    {
        if (!$publisherString)
        {
            return null;
        }
        // Odstraní čárku na konci
        $publisherString = rtrim($publisherString, ',');

        if (str_contains($publisherString, ':'))
        {
            $parts = explode(':', $publisherString);
            $publisherPart = $parts[1] ?? '';
            if (str_contains($publisherPart, ','))
            {
                return trim(explode(',', $publisherPart)[0]);
            }
            return trim($publisherPart);
        }
        return trim($publisherString);
    }
}
