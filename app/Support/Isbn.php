<?php

namespace App\Support;

/**
 * Pomocné funkce pro ISBN a SPN kódy (staré knihy bez ISBN, např. 13-030-68).
 */
class Isbn
{
    /**
     * Normalizuje ISBN z volného textu ("ISBN 0 946352 42 9" → "0-946352-42-9").
     * Vrací null, pokud nejde o 10/13místné ISBN. Se $strict navíc musí sedět kontrolní číslice –
     * pozor, některé starší české knihy mají ISBN vytištěné s chybnou kontrolní číslicí
     * (např. 80-237-6907-0) a knihovny je tak i evidují.
     */
    public static function normalize(?string $raw, bool $strict = true): ?string
    {
        if ($raw === null)
        {
            return null;
        }

        $raw = strtoupper(trim(preg_replace('/^\s*ISBN(?:-1[03])?:?/i', '', $raw)));
        $digits = preg_replace('/[^0-9X]/', '', $raw);

        if ($strict ? !self::isValid($digits) : !preg_match('/^(\d{9}[\dX]|\d{13})$/', $digits))
        {
            return null;
        }

        // ponecháme členění z tiráže, jen mezery/pomlčky sjednotíme na pomlčku
        return trim(preg_replace('/[^0-9X]+/', '-', $raw), '-');
    }

    /**
     * Ověří kontrolní číslici ISBN-10 nebo ISBN-13 (vstup jen číslice, případně X).
     */
    public static function isValid(string $digits): bool
    {
        if (preg_match('/^\d{9}[\dX]$/', $digits))
        {
            $sum = 0;
            for ($i = 0; $i < 10; $i++)
            {
                $value = $digits[$i] === 'X' ? 10 : (int) $digits[$i];
                $sum += $value * (10 - $i);
            }

            return $sum % 11 === 0;
        }

        if (preg_match('/^\d{13}$/', $digits))
        {
            $sum = 0;
            for ($i = 0; $i < 13; $i++)
            {
                $sum += (int) $digits[$i] * ($i % 2 === 0 ? 1 : 3);
            }

            return $sum % 10 === 0;
        }

        return false;
    }

    /**
     * Převede ISBN (10 i 13, s pomlčkami) na ISBN-13 bez oddělovačů. Neplatný formát → null.
     */
    public static function toIsbn13(?string $isbn): ?string
    {
        $digits = preg_replace('/[^0-9X]/', '', strtoupper((string) $isbn));

        if (preg_match('/^\d{13}$/', $digits))
        {
            return $digits;
        }

        if (!preg_match('/^\d{9}[\dX]$/', $digits))
        {
            return null;
        }

        $base = '978' . substr($digits, 0, 9);
        $sum = 0;
        for ($i = 0; $i < 12; $i++)
        {
            $sum += (int) $base[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return $base . ((10 - $sum % 10) % 10);
    }

    /**
     * Normalizuje SPN kód (tematická skupina-pořadí-rok, např. "13-030-68"). Neplatný → null.
     */
    public static function normalizeSpn(?string $raw): ?string
    {
        if ($raw === null)
        {
            return null;
        }

        $spn = preg_replace('/\s*[-–—\/]\s*/u', '-', trim($raw));

        if (preg_match('/\b(\d{2}-\d{2,3}-\d{2})\b/', $spn, $matches))
        {
            return $matches[1];
        }

        return null;
    }
}
