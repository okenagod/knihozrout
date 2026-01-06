<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\BookResource;
use App\Models\Book;
use Filament\Widgets\Widget;

class StartReview extends Widget
{
    protected static string $view = 'filament.widgets.start-review';

    protected static ?int $sort = 2;

    public function startReview()
    {
        $nextBook = Book::where('status', 'review')->first();

        if ($nextBook)
        {
            return redirect(BookResource::getUrl('edit', ['record' => $nextBook]));
        }

        \Filament\Notifications\Notification::make()
            ->title('Žádné knihy ke kontrole')
            ->info()
            ->send();
    }
}
