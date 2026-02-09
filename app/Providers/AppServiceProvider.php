<?php

namespace App\Providers;

use App\Models\Book;
use App\Observers\BookObserver;
use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ImageManager::class, function ()
        {
            return new ImageManager(new Driver);
        });

        $this->app->singleton(ImageAnnotatorClient::class, function ()
        {
            return new ImageAnnotatorClient([
                                                'credentials' => base_path(config('services.google.vision_credentials')),
                                            ]);
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
