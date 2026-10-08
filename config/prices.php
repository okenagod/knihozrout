<?php

use App\Services\PriceServices\KnihobotSource;
use App\Services\PriceServices\TrhKnihSource;

return [

    // Dohledání cen po zpracování knihy (FetchBookPricesJob)
    'enabled' => env('PRICES_ENABLED', true),

    // Zdroje s vlastním parserem (rychlé, bez LLM), v tomto pořadí
    'sources' => [
        TrhKnihSource::class,
        KnihobotSource::class,
    ],

    // E-shopy čtené přes LLM (LlmShopSource) – stačí název a URL hledání, {query} = název knihy.
    // Každý obchod = 1 volání textového LLM (services.llm.model), řádově 30–60 s.
    'llm_shops_enabled' => env('PRICES_LLM_SHOPS_ENABLED', true),
    'llm_shops' => [
        ['name' => 'Knihy Dobrovský', 'search_url' => 'https://www.knihydobrovsky.cz/vyhledavani?search={query}'],
        ['name' => 'Martinus', 'search_url' => 'https://www.martinus.cz/search?q={query}'],
    ],

    // Kolik znaků stránky poslat do LLM a jak velký kontext modelu nastavit (Ollama má výchozí jen 2–4k tokenů)
    'llm_max_chars' => 24000,
    'llm_num_ctx' => 16384,

    // Kolik vydání jednoho titulu na Trhu knih procházet (každé = 1 request na detail)
    'trhknih_max_issues' => 3,

    'timeout' => 20,
    'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36',
];
