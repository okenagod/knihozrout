<?php

namespace App\Filament\Resources\BookResource\RelationManagers;

use App\Jobs\FetchBookPricesJob;
use App\Models\Book;
use App\Models\BookPrice;
use Filament\Forms\Components as Comp;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Tabulka cen v detailu knihy. Ceny plní FetchBookPricesJob, admin může přidat i vlastní (is_manual).
 * Min/max cena knihy se přepočítá automaticky při každé změně (BookPrice::booted).
 */
class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    protected static ?string $title = 'Ceny';

    protected static ?string $modelLabel = 'cena';

    protected static ?string $pluralModelLabel = 'ceny';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Comp\TextInput::make('value')->label('Cena')->numeric()->required()->prefix('Kč'),
            Comp\TextInput::make('source')->label('Zdroj')->required()->default('Ručně'),
            Comp\TextInput::make('url')->label('URL')->url()->maxLength(2048)->columnSpanFull(),
            Comp\TextInput::make('title')->label('Název nabídky'),
            Comp\TextInput::make('condition')->label('Stav'),
            Comp\Hidden::make('currency')->default('CZK'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('source')
            ->defaultSort('value')
            ->poll('15s') // job doplňuje ceny na pozadí
            ->columns([
                Tables\Columns\TextColumn::make('value')
                    ->label('Cena')
                    ->money(fn ($record) => $record->currency, locale: 'cs')
                    ->sortable(),
                Tables\Columns\TextColumn::make('source')
                    ->label('Zdroj')
                    ->badge()
                    ->color(fn ($record) => $record->is_manual ? 'warning' : 'gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Nabídka')
                    ->description(fn ($record) => $record->condition)
                    ->wrap()
                    ->limit(80),
                Tables\Columns\TextColumn::make('url')
                    ->label('Odkaz')
                    ->formatStateUsing(fn (?string $state) => $state ? parse_url($state, PHP_URL_HOST) : null)
                    ->url(fn ($record) => $record->url, shouldOpenInNewTab: true)
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->iconPosition('after')
                    ->color('primary'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Načteno')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('source')
                    ->label('Zdroj')
                    ->options(fn () => BookPrice::where('book_id', $this->getOwnerRecord()->getKey())->distinct()->pluck('source', 'source')->all()),
            ])
            ->headerActions([
                Tables\Actions\Action::make('fetchPrices')
                    ->label('Načíst ceny')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->modalDescription('Automaticky získané ceny se nahradí novými (ručně zadané zůstanou). Hledání běží na pozadí, může trvat i několik minut.')
                    ->action(function () {
                        /** @var Book $book */
                        $book = $this->getOwnerRecord();
                        FetchBookPricesJob::dispatch($book);

                        Notification::make()
                            ->title('Hledání cen spuštěno')
                            ->body('Tabulka se obnoví sama, min/max cenu uvidíte po obnovení stránky.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\CreateAction::make()
                    ->label('Přidat cenu')
                    ->mutateFormDataUsing(fn (array $data) => $data + ['is_manual' => true])
                    ->after(fn () => $this->dispatch('prices-updated')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('')
                    ->after(fn () => $this->dispatch('prices-updated')),
                Tables\Actions\DeleteAction::make()->label('')
                    ->after(fn () => $this->dispatch('prices-updated')),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->after(fn () => $this->dispatch('prices-updated')),
            ])
            ->emptyStateHeading('Zatím žádné ceny')
            ->emptyStateDescription('Ceny se hledají automaticky po zpracování knihy, nebo je načtěte tlačítkem výše.');
    }
}
