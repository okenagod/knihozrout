<?php

namespace App\Providers;

use App\Models\Book;
use App\Observers\BookObserver;
use App\Services\BookLlmService;
use App\Services\BookOcrService;
use App\Services\BookScanServiceInterface;
use App\Services\Llm\LlmConnector;
use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ImageManager::class, function () {
            return new ImageManager(new Driver);
        });

        $this->app->singleton(ImageAnnotatorClient::class, function () {
            // bez souboru s credentials (např. při docker buildu nebo s BOOK_SCAN_DRIVER=llm) nepadat –
            // klient pak použije Application Default Credentials a případná chyba přijde až při volání OCR
            $credentials = base_path((string) config('services.google.vision_credentials'));

            return new ImageAnnotatorClient(is_file($credentials) ? ['credentials' => $credentials] : []);
        });

        $this->app->singleton(LlmConnector::class, function () {
            return new LlmConnector(
                baseUrl: config('services.llm.base_url'),
                defaultModel: config('services.llm.model'),
                timeout: config('services.llm.timeout'),
                keepAlive: config('services.llm.keep_alive'),
            );
        });

        // čtení tiráže – Google Vision OCR nebo vision LLM (BOOK_SCAN_DRIVER)
        $this->app->bind(BookScanServiceInterface::class, function ($app) {
            return match (config('services.book_scan.driver'))
            {
                'llm' => $app->make(BookLlmService::class),
                default => $app->make(BookOcrService::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (!app()->environment('local'))
        {
            URL::forceScheme('https');
        }

        Book::observe(BookObserver::class);
    }
}
