<?php

namespace App\Services\PriceServices;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

abstract class AbstractPriceSource implements PriceSourceInterface
{
    /**
     * HTTP klient s hlavičkami prohlížeče – některé obchody jinak vrací 403.
     */
    protected function http(): PendingRequest
    {
        return Http::withHeaders([
            'User-Agent' => config('prices.user_agent'),
            'Accept-Language' => 'cs-CZ,cs;q=0.9',
        ])
            ->timeout(config('prices.timeout', 20))
            ->retry(2, 1000, throw: false);
    }

    /**
     * Stáhne stránku, při chybě vyhodí výjimku (PriceService ji zaloguje a pokračuje dalším zdrojem).
     */
    protected function get(string $url, array $query = []): string
    {
        // pozor: get($url, []) by zahodil query string, který už je v $url
        return $this->http()->get($url, $query ?: null)->throw()->body();
    }

    /**
     * "1 234,50 Kč" → 1234.5
     */
    protected function parsePrice(string $value): ?float
    {
        $value = preg_replace('/[^\d,.]/', '', str_replace(["\u{00A0}", ' '], '', $value));
        $value = str_replace(',', '.', $value);

        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }
}
