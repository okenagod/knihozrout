<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\BookResource;
use App\Models\Book;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use Filament\Widgets\Widget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;

class StartReview extends Widget
{
    protected static string $view = 'filament.widgets.start-review';

    protected static ?int $sort = 2;

    public function startReview(): RedirectResponse|Redirector
    {
        $nextBook = Book::where('status', 'review')->first();

        if ($nextBook)
        {
            return redirect(BookResource::getUrl('edit', ['record' => $nextBook]));
        }

        Notification::make()
            ->title('Žádné knihy ke kontrole')
            ->info()
            ->send();

        return redirect(Dashboard::getUrl());
    }
}
