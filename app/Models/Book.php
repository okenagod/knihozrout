<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    protected $guarded = [];

    protected $casts = [
        'photos' => 'array',
        'minPrice' => 'decimal:2',
        'maxPrice' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(BookPrice::class);
    }

    /**
     * Nastaví minPrice/maxPrice podle cen v Kč. Bez cen hodnoty nemění (mohl je zadat admin).
     */
    public function refreshPriceRange(): void
    {
        $prices = $this->prices()->where('currency', 'CZK');

        if (!$prices->exists())
        {
            return;
        }

        $this->minPrice = $prices->min('value');
        $this->maxPrice = $prices->max('value');
        $this->saveQuietly();
    }
}
