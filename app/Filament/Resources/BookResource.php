<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookResource\Pages;
use App\Filament\Resources\BookResource\RelationManagers;
use App\Models\Book;
use App\Services\LibraryService;
use Filament\Forms\Components as Comp;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookResource extends Resource
{
    protected static ?string $model = Book::class;

    protected static ?string $modelLabel = 'Kniha';

    protected static ?string $pluralModelLabel = 'Knihy';

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    public static function form(Form $form): Form
    {
        // main photo for OCR
        $createMainPhoto = Comp\FileUpload::make('main_photo')
            ->image()
            ->label('ISBN / Tiráž knihy')
            ->directory('book-scans')
            ->extraInputAttributes(['capture' => 'environment'])
            ->extraAttributes([
                'class' => 'fi-fo-file-upload-hide-preview',
            ])
            ->previewable(false);

        // other additional photos
        $createPhotos = Comp\FileUpload::make('photos')
            ->multiple()
            ->image()
            ->label('Ostatní fotky')
            ->directory('book-scans')
            ->extraInputAttributes([
                'capture' => 'environment',
            ])
            ->extraAttributes([
                'class' => 'fi-fo-file-upload-hide-preview',
            ])
            ->previewable(false);

        // template for displaying photos in review form
        $photoDisplay = Comp\Placeholder::make('photos_display')
            ->label('')
            ->content(fn ($record) => view('filament.components.photo-display', [
                'photos' => array_merge([$record->main_photo], $record->photos ?? []),
            ]));

        $ocr = Comp\Textarea::make('ocr_full_text')
            ->label('Surový výstup OCR / LLM')
            ->rows(15)
            ->readOnly();

        $antique = Comp\Checkbox::make('is_antique')
            ->label('Kniha nemá ISBN (knihy před r. 1989)')
            ->helperText('Zaškrtněte, pokud kniha nemá ISBN (vydáno před r. 1989)');

        $classification = Comp\Select::make('classification')->label('Klasifikace')
            ->options([
                '1' => '1 - Jako nová',
                '2' => '2 - Velmi dobrý',
                '3' => '3 - Opotřebená',
                '4' => '4 - Poškozená',
                '5' => '5 - Salátové vydání / Torzo',
            ]);

        $binNumber = Comp\TextInput::make('bin_number')
            ->numeric()
            ->label('Číslo přepravky')
            ->default(fn () => \App\Models\Book::latest()->first()?->bin_number)
            ->required();

        if ($form->getOperation() === 'create')
        {
            return $form->schema([
                Comp\Section::make('Rychlý sběr dat')
                    ->schema([
                        Comp\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->label('Uživatel')
                            ->searchable()
                            ->preload()
                            ->required(),
                        $binNumber,
                        $classification,
                        $antique,
                        $createMainPhoto,
                        $createPhotos,
                    ]),
            ]);
        }
        else
        {
            // FORMULÁŘ PRO EDITACI (Kontrola u PC)
            $bookInfo = [
                Comp\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->label('Uživatel')
                    ->searchable()
                    ->preload()
                    ->required(),
                $binNumber,
                Comp\TextInput::make('title')->label('Název'),
                Comp\TextInput::make('author')->label('Autor'),
                Comp\TextInput::make('isbn')->label('ISBN/SPN')
                    ->helperText('U knih před r. 1989 zadejte kód nakladatelství (např. SPN kód)')
                    ->suffixAction(
                        Comp\Actions\Action::make('searchNkp')
                            ->icon('heroicon-m-magnifying-glass')
                            ->tooltip('Hledat v NKP')
                            ->action(function (Set $set, $state, LibraryService $libraryService) {
                                if (empty($state))
                                {
                                    Notification::make()
                                        ->title('Zadejte ISBN')
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                $result = $libraryService->searchByIsbn($state);

                                if ($result)
                                {
                                    $set('title', $result->title);
                                    $set('author', $result->author);
                                    $set('publisher', $result->publisher);
                                    $set('year', $result->year);

                                    Notification::make()
                                        ->title('Data z NKP načtena')
                                        ->success()
                                        ->send();
                                }
                                else
                                {
                                    Notification::make()
                                        ->title('Kniha nebyla v NKP nalezena')
                                        ->warning()
                                        ->send();
                                }
                            })
                    ),
                $antique,
                Comp\TextInput::make('year')->label('Rok vydání'),
                Comp\TextInput::make('publisher')->label('Vydavatel'),
                $classification,
                Comp\Select::make('status')
                    ->options([
                        'new' => 'Nové',
                        'review' => 'Ke kontrole',
                        'done' => 'Hotovo',
                    ])->default('new'),
                Comp\Textarea::make('note')->label('Poznámka')->rows(3),
                Comp\TextInput::make('minPrice')->label('Minimální cena')->numeric()->prefix('Kč')
                    ->helperText('Doplní se z tabulky cen níže'),
                Comp\TextInput::make('maxPrice')->label('Maximální cena')->numeric()->prefix('Kč'),
            ];

            return $form->schema([
                Comp\Split::make([
                    $photoDisplay
                        ->extraAttributes([
                            'class' => 'sticky top-5 flex-shrink-0',
                            'style' => 'max-height: calc(100vh - 80px); overflow-y: auto;',
                        ]),
                    Comp\Grid::make(2)
                        ->schema([
                            Comp\Section::make('Nalezený text (OCR)')
                                ->schema([$ocr])
                                ->columnSpan(1),
                            Comp\Section::make('Informace o knize (vyplní OCR/Admin)')
                                ->schema($bookInfo)
                                ->columnSpan(1),
                        ])
                        ->grow()
                        ->extraAttributes([
                            'style' => 'flex-grow: 1; min-width: 0;',
                        ]),
                ])
                    ->from('md')
                    ->columnSpanFull(),
            ]);
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('photos')
                    ->label('Foto')
                    ->height(120)
                    ->grow(false)
                    ->circular()
                    ->stacked()
                    ->limit(1),
                Tables\Columns\TextColumn::make('title')
                    ->label('Název')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->author),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Vlastník')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('isbn')
                    ->label('ISBN / SPN')
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('bin_number')
                    ->label('Přepravka')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Stav')
                    ->badge()
                    ->color(fn (string $state): string => match ($state)
                    {
                        'new' => 'info',
                        'review' => 'warning',
                        'done' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_antique')
                    ->label('Má ISBN')
                    ->boolean()
                    ->trueIcon('heroicon-o-x-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Přidáno')
                    ->dateTime('d.m. H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->label('Uživatel')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('bin_number')
                    ->label('Podle přepravky')
                    ->options(fn () => \App\Models\Book::pluck('bin_number', 'bin_number')->toArray()),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Stav zpracování')
                    ->options([
                        'new' => 'Nové',
                        'review' => 'Ke kontrole',
                        'done' => 'Hotovo',
                    ]),
                Tables\Filters\TernaryFilter::make('is_antique')
                    ->label('Má ISBN')
                    ->trueLabel('Ano (běžné knihy)')
                    ->falseLabel('Ne (staré tisky/antikvární)')
                    ->queries(
                        true: fn (Builder $query) => $query->where('is_antique', false),
                        false: fn (Builder $query) => $query->where('is_antique', true),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('view_photos')
                    ->label('Náhled')
                    ->icon('heroicon-o-camera')
                    ->color('info')
                    ->modalContent(fn ($record) => view('filament.components.photo-display', ['photos' => array_merge($record->photos, [$record->main_photo])]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Zavřít'),
                Tables\Actions\DeleteAction::make()->label(''),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\BulkAction::make('markAsDone')
                    ->label('Označit jako hotové')
                    ->icon('heroicon-o-check-circle')
                    ->action(fn (\Illuminate\Database\Eloquent\Collection $records) => $records->each->update(['status' => 'done']))
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PricesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBooks::route('/'),
            'create' => Pages\CreateBook::route('/create'),
            'edit' => Pages\EditBook::route('/{record}/edit'),
        ];
    }
}
