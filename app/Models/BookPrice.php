<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Book|null $book
 *
 * Cena knihy nalezená v jednom zdroji (antikvariát, knihkupectví) nebo zadaná ručně.
 */
class BookPrice extends Model
{
    protected $guarded = [];

    protected $casts = [
        'value' => 'decimal:2',
        'is_manual' => 'boolean',
    ];

    protected static function booted(): void
    {
        // min/max cena knihy se vždy dopočítá z jejích cen
        static::saved(fn (BookPrice $price) => $price->book?->refreshPriceRange());
        static::deleted(fn (BookPrice $price) => $price->book?->refreshPriceRange());
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
