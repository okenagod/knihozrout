<?php

namespace App\Observers;

use App\Jobs\ProcessBookJob;
use App\Models\Book;
use App\Services\ImageProcessingService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class BookObserver
{
    public function __construct(
        protected readonly ImageProcessingService $imageService
    ) {}

    /**
     * Handle the Book "created" event.
     */
    public function created(Book $book): void
    {
        $this->processBookImages($book);
        ProcessBookJob::dispatch($book);
    }

    /**
     * Zpracuje obrázky knihy: přesune do original a vytvoří WebP.
     */
    protected function processBookImages(Book $book): void
    {
        // Zpracování hlavní fotky
        if ($book->main_photo)
        {
            $newPath = $this->imageService->processImage($book->main_photo);
            if ($newPath)
            {
                $book->main_photo = $newPath;
            }
        }

        // Zpracování ostatních fotek
        if ($book->photos && is_array($book->photos))
        {
            $newPhotos = [];
            foreach ($book->photos as $photo)
            {
                $newPath = $this->imageService->processImage($photo);
                $newPhotos[] = $newPath ?: $photo;
            }
            $book->photos = $newPhotos;
        }

        // Uložíme změněné cesty (bez spouštění eventů, abychom se nezacyklili)
        $book->saveQuietly();
    }

    /**
     * Handle the Book "updated" event.
     */
    public function updated(Book $book): void
    {
        //
    }

    /**
     * Handle the Book "deleted" event.
     */
    public function deleted(Book $book): void
    {
        // 1. Smazání hlavní fotky a jejího originálu
        if ($book->main_photo)
        {
            Storage::disk('public')->delete($book->main_photo);
            $this->deleteOriginal($book->main_photo);
        }

        // 2. Smazání ostatních fotek a jejich originálů
        if ($book->photos && is_array($book->photos))
        {
            foreach ($book->photos as $photo)
            {
                Storage::disk('public')->delete($photo);
                $this->deleteOriginal($photo);
            }
        }
    }

    /**
     * Smaže originální verzi souboru, pokud existuje.
     */
    protected function deleteOriginal(string $path): void
    {
        $directory = dirname($path);
        $filename = basename($path);
        $filenameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        $originalFolder = $directory . '/original/';

        File::delete(File::glob(storage_path('app/public/' . $originalFolder . $filenameWithoutExt . '.*')));
    }

    /**
     * Handle the Book "restored" event.
     */
    public function restored(Book $book): void
    {
        //
    }

    /**
     * Handle the Book "force deleted" event.
     */
    public function forceDeleted(Book $book): void
    {
        //
    }
}
