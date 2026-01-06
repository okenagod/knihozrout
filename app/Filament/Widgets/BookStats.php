<?php

namespace App\Filament\Widgets;

use App\Models\Book;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BookStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Celkem knih', Book::count())
                ->description('Celkový počet v systému')
                ->icon('heroicon-o-book-open'),
            Stat::make('Ke kontrole', Book::where('status', 'review')->count())
                ->description('Čeká na schválení')
                ->icon('heroicon-o-clock')
                ->color('warning'),
            Stat::make('Hotovo', Book::where('status', 'done')->count())
                ->description('Zpracované knihy')
                ->icon('heroicon-o-check-circle')
                ->color('success'),
        ];
    }
}
