<?php

namespace App\Support;

/**
 * Převod HTML stránky na kompaktní text pro LLM – bez skriptů, stylů a navigace,
 * odkazy zachované ve tvaru [text](absolutní url), aby LLM mohlo vrátit odkaz na nabídku.
 */
class HtmlText
{
    public static function fromHtml(string $html, string $baseUrl): string
    {
        $baseUrl = rtrim($baseUrl, '/');

        $html = preg_replace('#<(script|style|svg|noscript|head|header|footer|nav)\b.*?</\1>#is', '', $html);

        $html = preg_replace_callback('#<a\b[^>]*href="([^"\#]+)"[^>]*>(.*?)</a>#is', function (array $m) use ($baseUrl) {
            $text = trim(preg_replace('/\s+/u', ' ', strip_tags($m[2])));
            if ($text === '')
            {
                return ' ';
            }

            return ' [' . $text . '](' . self::absoluteUrl(html_entity_decode($m[1]), $baseUrl) . ') ';
        }, $html);

        $html = preg_replace('#<(br|/p|/div|/li|/tr|/h\d)\b[^>]*>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = preg_replace('/\s*\n\s*/u', "\n", $text);

        return trim($text);
    }

    /**
     * Vyřízne z textu okno začínající kousek před prvním výskytem $needle (např. názvu knihy).
     * Výsledky vyhledávání bývají až za dlouhými filtry a menu, celou stránku do kontextu malého modelu nedáme.
     * Pokud $needle v textu není, vrací null.
     */
    public static function window(string $text, string $needle, int $length, int $before = 1500): ?string
    {
        // bez diakritiky – e-shopy píší názvy různě (Špión × Špion); u češtiny Str::ascii zachová délku textu
        $position = mb_strpos(BookMatcher::fold($text, keepPunctuation: true), BookMatcher::fold($needle, keepPunctuation: true));

        if ($position === false)
        {
            return null;
        }

        return mb_substr($text, max(0, $position - $before), $length);
    }

    public static function absoluteUrl(string $url, string $baseUrl): string
    {
        if (preg_match('#^https?://#i', $url))
        {
            return $url;
        }

        if (str_starts_with($url, '//'))
        {
            return 'https:' . $url;
        }

        $parts = parse_url($baseUrl);
        $root = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');

        return $root . '/' . ltrim($url, '/');
    }
}
