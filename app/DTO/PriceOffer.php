<?php

namespace App\DTO;

/**
 * Jedna nabídka knihy nalezená ve zdroji cen.
 */
class PriceOffer
{
    public function __construct(
        public readonly float $value,
        public readonly string $source,
        public readonly ?string $url = null,
        public readonly ?string $title = null,
        public readonly ?string $condition = null,
        public readonly string $currency = 'CZK',
    ) {}

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'currency' => $this->currency,
            'source' => $this->source,
            'url' => $this->url,
            'title' => $this->title,
            'condition' => $this->condition,
        ];
    }
}
