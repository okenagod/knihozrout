<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

class ImageProcessingService
{
    public function __construct(
        private ImageManager $manager
    ) {}

    /**
     * Zpracuje obrázek: uloží originál do podslozky 'original' a vytvoří optimalizovaný WebP v hlavním slozce.
     *
     * @param  string      $path Relativní cesta k souboru v rámci 'public' disku.
     * @return string|null Nová cesta k optimalizovanému souboru (WebP).
     */
    public function processImage(string $path): ?string
    {
        if (!Storage::disk('public')->exists($path))
        {
            return null;
        }

        //        $fullPath = Storage::disk('public')->path($path);
        $directory = dirname($path);
        $filename = basename($path);
        $filenameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);

        // 1. Vytvoření složky 'original', pokud neexistuje
        $originalDirectory = $directory . '/original';
        if (!Storage::disk('public')->exists($originalDirectory))
        {
            Storage::disk('public')->makeDirectory($originalDirectory);
        }

        // 2. Přesun původního souboru do 'original'
        $originalPath = $originalDirectory . '/' . $filename;
        Storage::disk('public')->move($path, $originalPath);

        // 3. Vytvoření optimalizované verze (WebP)
        $optimizedFilename = $filenameWithoutExt . '.webp';
        $optimizedPath = $directory . '/' . $optimizedFilename;
        $optimizedFullPath = Storage::disk('public')->path($optimizedPath);

        try
        {
            $image = $this->manager->read(Storage::disk('public')->path($originalPath));

            // Změna velikosti (např. max šířka 1600px, zachování poměru stran)
            $image->scale(width: 1600);

            // Uložení jako WebP s rozumnou kvalitou
            $image->toWebp(quality: 80)->save($optimizedFullPath);

            return $optimizedPath;
        }
        catch (\Exception $e)
        {
            \Log::error('Image processing error: ' . $e->getMessage());

            // Pokud selže zpracování, vrátíme původní soubor zpět (nebo necháme v original a vrátíme cestu k němu)
            // Ale raději zkusíme vrátit aspoň něco.
            return $originalPath;
        }
    }

    /**
     * Zkusí najít originální soubor pro danou cestu. Pokud neexistuje, vrátí cestu k optimalizovanému.
     *
     * @param  string $path Relativní cesta k optimalizovanému souboru v rámci 'public' disku.
     * @return string Absolutní cesta k souboru.
     */
    public function getOriginalPath(string $path): string
    {
        $directory = dirname($path);
        $filenameWithoutExt = pathinfo($path, PATHINFO_FILENAME);

        $originalFolder = $directory . '/original/';

        if (Storage::disk('public')->exists($originalFolder))
        {
            $files = Storage::disk('public')->files($originalFolder);
            foreach ($files as $file)
            {
                if (pathinfo($file, PATHINFO_FILENAME) === $filenameWithoutExt)
                {
                    return Storage::disk('public')->path($file);
                }
            }
        }

        return Storage::disk('public')->path($path);
    }
}
