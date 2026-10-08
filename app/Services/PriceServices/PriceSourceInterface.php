<?php

namespace App\Services\PriceServices;

use App\DTO\PriceOffer;
use App\Models\Book;

interface PriceSourceInterface
{
    /**
     * Název zdroje, ukládá se k ceně (book_prices.source).
     */
    public function name(): string;

    /**
     * Najde nabídky dané knihy. Chyby komunikace smí vyhodit výjimkou, PriceService je zaloguje.
     *
     * @return PriceOffer[]
     */
    public function search(Book $book): array;
}
