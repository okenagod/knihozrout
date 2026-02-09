<?php

namespace App\Services;

use App\DTO\BookData;
use App\Models\Book;
use App\Services\LibraryServices\GoogleBooksConnector;
use App\Services\LibraryServices\KnihovnyCzConnector;
use App\Services\LibraryServices\LibraryConnectorInterface;
use App\Services\LibraryServices\NkpConnector;
use App\Services\LibraryServices\OpenLibraryConnector;
use Symfony\Component\VarDumper\VarDumper;

class LibraryService
{
    public function __construct(
        private KnihovnyCzConnector $knihovnyCzConnector,
        private OpenLibraryConnector $openLibraryConnector,
        private GoogleBooksConnector $googleBooksConnector,
        private NkpConnector $nkpConnector,
    ) {}

    private function getConnectors(): array
    {
        return [
            $this->knihovnyCzConnector,
            $this->openLibraryConnector,
            $this->googleBooksConnector,
            $this->nkpConnector,
        ];
    }

    public function processBook(Book $book)
    {
        if (!$book->isbn)
        {
            return;
        }

        $bookData = $this->searchByIsbn($book->isbn);

        if ($bookData)
        {
            $book->title = $bookData->title;
            $book->author = $bookData->author;
            $book->publisher = $bookData->publisher;
            $book->year = $bookData->year;

            $book->save();
        }
    }

    /**
     * Vyhledá knihu v podle ISBN.
     */
    public function searchByIsbn(string $isbn): ?BookData
    {
        foreach ($this->getConnectors() as $connector)
        {
            $result = $connector->fetch($isbn);
            if ($result)
            {
                return $result;
            }
        }

        return null;
    }
}
