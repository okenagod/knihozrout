<?php

namespace App\Filament\Resources\BookResource\Pages;

use App\Filament\Resources\BookResource;
use App\Models\Book;
use App\Services\BookOcrService;
use App\Services\LibraryService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/**
 * @property Book $record
 */
class EditBook extends EditRecord
{
    protected static string $resource = BookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('runOcr')
                ->label('Spustit OCR')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->action(function (BookOcrService $ocrService) {
                    $ocrService->processBook($this->record);
                    $this->refreshFormData(['ocr_full_text', 'isbn']);
                    Notification::make()->title('OCR bylo dokončeno.')->success()->send();
                }),

            Actions\Action::make('findInfo')
                ->label('Najít informace')
                ->icon('heroicon-o-magnifying-glass')
                ->color('gray')
                ->action(function (LibraryService $libraryService) {
                    $libraryService->processBook($this->record);
                    $this->refreshFormData(['title', 'author', 'publisher', 'year']);
                    Notification::make()->title('Vyhledávání dokončeno.')->success()->send();
                }),

            Actions\Action::make('approve')
                ->label('Schválit')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->action(function () {
                    $this->data['status'] = 'done';
                    $this->save();

                    Notification::make()
                        ->title('Kniha byla schválena a uložena')
                        ->success()
                        ->send();

                    return redirect($this->getResource()::getUrl('index'));
                }),
            Actions\Action::make('approveAndNext')
                ->label('Schválit a další')
                ->color('success')
                ->icon('heroicon-o-forward')
                ->action(function () {
                    $this->data['status'] = 'done';
                    $this->save();

                    /** @var Book|null $nextBook */
                    $nextBook = Book::where('status', 'review')
                        ->where('id', '!=', $this->record->getKey())
                        ->first();

                    Notification::make()
                        ->title('Kniha schválena')
                        ->success()
                        ->send();

                    if ($nextBook)
                    {
                        return redirect($this->getResource()::getUrl('edit', ['record' => $nextBook]));
                    }

                    Notification::make()
                        ->title('Žádné knihy ke kontrole')
                        ->info()
                        ->send();

                    return redirect($this->getResource()::getUrl('index'));
                }),
            Actions\Action::make('save')
                ->label('Uložit')
                ->action('save'),
            $this->getCancelFormAction(),
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
