<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Volné porovnání názvů a autorů knih z různých zdrojů (diakritika, velikost písmen, podtituly).
 */
class BookMatcher
{
    /**
     * Malá písmena bez diakritiky; bez $keepPunctuation navíc jen písmena, číslice a jednoduché mezery.
     */
    public static function fold(string $value, bool $keepPunctuation = false): string
    {
        $value = mb_strtolower(Str::ascii($value));

        if ($keepPunctuation)
        {
            return $value;
        }

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value));
    }

    /**
     * Hlavní název bez podtitulu ("Z očí do očí : rozhovory…" → "Z očí do očí").
     */
    public static function mainTitle(string $title): string
    {
        return trim(preg_split('/\s+[:\/]\s+|\s*[\[(]/u', $title)[0]);
    }

    /**
     * Odpovídá nalezený název hledanému? Shoda hlavních názvů, případně nalezený název začíná hledaným
     * (podtitul bez dvojtečky). Opačně ne – kratší "Špion" není "Špion, jemuž nevěřili".
     */
    public static function titleMatches(?string $found, string $wanted): bool
    {
        $found = self::fold(self::mainTitle((string) $found));
        $wanted = self::fold(self::mainTitle($wanted));

        if ($found === '' || $wanted === '')
        {
            return false;
        }

        return $found === $wanted || str_starts_with($found . ' ', $wanted . ' ');
    }

    /**
     * Odpovídá autor? Neznámý autor (u knihy nebo v nabídce) se nepočítá jako neshoda.
     * Porovnává se příjmení prvního autora knihy.
     */
    public static function authorMatches(?string $found, ?string $wanted): bool
    {
        $surname = self::surname($wanted);

        if ($surname === null || blank($found))
        {
            return true;
        }

        return str_contains(' ' . self::fold($found) . ' ', ' ' . $surname . ' ');
    }

    /**
     * Příjmení prvního autora ("Peter Cave, Růžena Loulová" → "cave", "Moravec, František" → "moravec").
     */
    public static function surname(?string $author): ?string
    {
        if (blank($author))
        {
            return null;
        }

        // "Příjmení, Jméno" (katalogový zápis) × "Jméno Příjmení, Jméno2 Příjmení2"
        $parts = array_map('trim', explode(',', $author));
        $first = (count($parts) === 2 && !str_contains($parts[0], ' ') && !str_contains($parts[1], ' '))
            ? $parts[0]
            : collect(explode(' ', $parts[0]))->last();

        $surname = self::fold($first);

        return $surname === '' ? null : $surname;
    }
}
