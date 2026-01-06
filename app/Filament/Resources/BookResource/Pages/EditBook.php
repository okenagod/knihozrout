<?php

namespace App\Filament\Resources\BookResource\Pages;

use App\Filament\Resources\BookResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBook extends EditRecord
{
    protected static string $resource = BookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // approve book and return to book list
            Actions\Action::make('approve')
                ->label('Schválit')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->action(function () {
                    $this->data['status'] = 'done';
                    $this->save();

                    \Filament\Notifications\Notification::make()
                        ->title('Kniha byla schválena a uložena')
                        ->success()
                        ->send();

                    return redirect($this->getResource()::getUrl('index'));
                }),
            // approve book and go to next review
            Actions\Action::make('approveAndNext')
                ->label('Schválit a další')
                ->color('success')
                ->icon('heroicon-o-forward')
                ->action(function () {
                    $this->data['status'] = 'done';
                    $this->save();

                    /** @var \App\Models\Book|null $nextBook */
                    $nextBook = \App\Models\Book::where('status', 'review')
                        ->where('id', '!=', $this->record->getKey())
                        ->first();

                    \Filament\Notifications\Notification::make()
                        ->title('Kniha schválena')
                        ->success()
                        ->send();

                    if ($nextBook)
                    {
                        return redirect($this->getResource()::getUrl('edit', ['record' => $nextBook]));
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('Žádné knihy ke kontrole')
                        ->info()
                        ->send();

                    return redirect($this->getResource()::getUrl('index'));
                }),
            Actions\Action::make('save')
                ->label('Uložit')
                ->action('save'),
            $this->getCancelFormAction(),
            //			Actions\DeleteAction::make(),
        ];
    }

    /**
     * buttons moved to header section
     */
    protected function getFormActions(): array
    {
        return [];
    }
}
