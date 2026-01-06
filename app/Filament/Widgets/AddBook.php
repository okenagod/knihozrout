<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\BookResource;
use Filament\Widgets\Widget;

class AddBook extends Widget
{
    protected static string $view = 'filament.widgets.add-book';

    protected static ?int $sort = 1;

    public function goToCreate()
    {
        return redirect(BookResource::getUrl('create'));
    }
}
