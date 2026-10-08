# Knihožrout – digitalizace a evidence archivu knih

Interní systém pro zpracování archivu knih. Uživatel (typicky admin na mobilu) nafotí knihu
(tiráž / ISBN, obálku, hřbet…), zadá číslo přepravky a stav knihy a uloží ji. Na pozadí pak:

1. fotky se převedou na optimalizovaný WebP (originál se zachová),
2. tiráž (hlavní fotka) se přečte – podle `BOOK_SCAN_DRIVER` buď:
   - `google`: **Google Cloud Vision OCR** + regex na ISBN (u starých knih bez ISBN kód SPN, např. `14-45-76`), nebo
   - `llm`: **lokální vision LLM** (Ollama, `qwen2.5vl:7b`) vrátí rovnou přepis textu i strukturovaný JSON
     (název, autor, nakladatel, rok, ISBN, SPN) – bez regexů,
3. podle ISBN se kniha dohledá v knihovních API a doplní se název, autor, vydavatel, rok,
4. kniha přejde do stavu `review` a admin ji u PC zkontroluje / doplní a označí jako `done`.

Jazyk projektu: UI, komentáře i commity jsou česky. Locale `cs`.

## Stack

- PHP 8.2+ (Sail runtime 8.5, produkční image PHP 8.3-fpm-alpine), **Laravel 12** (README chybně uvádí 11)
- **Filament v3** – admin panel na `/admin` (top navigation, full width), plugin `njxqlus/filament-lightbox`
- `google/cloud-vision` (OCR), `intervention/image` s GD driverem (WebP)
- Lokální LLM přes **Ollama API** (vlastní `LlmConnector`, žádná knihovna) – modely `qwen2.5vl:7b` (vision), `qwen3-coder:30b` (text)
- MySQL 8.4 (Sail i produkce), fronta `database`, Tailwind 4 + Vite pro frontend
- Kód je v adresáři `knihozrout/knihozrout/` (git repo), vnější `knihozrout/` je jen obal.

## Architektura – kde co je

| Oblast | Soubor |
|---|---|
| Model knihy | `app/Models/Book.php` (`$guarded = []`, `photos` cast na array) |
| Uživatelé, role | `app/Models/User.php` – `role` = `admin` / `user`; do Filamentu smí jen `admin` |
| Spuštění pipeline | `app/Observers/BookObserver.php` – `created`: zpracuje fotky + `ProcessBookJob::dispatch()`; `deleted`: smaže fotky i originály |
| Job | `app/Jobs/ProcessBookJob.php` – `BookScanServiceInterface` → `refresh()` → LibraryService; `$timeout = 600` |
| Obrázky | `app/Services/ImageProcessingService.php` – originál do `<dir>/original/`, WebP max šířka 1600 px, q80 |
| Čtení tiráže – rozhraní | `app/Services/BookScanServiceInterface.php` – `processBook(Book)`; implementaci vybírá `AppServiceProvider` podle `services.book_scan.driver` |
| OCR + parsování (google) | `app/Services/BookOcrService.php` – `processBook()`, `updateRegexText()`, `getRegexIsbn()`, `getTitle()` |
| Čtení tiráže přes LLM (llm) | `app/Services/BookLlmService.php` – `processBook()`, `scanImage(path): BookScanData`, `toScanData()`, `fillBook()` |
| LLM connector (obecný) | `app/Services/Llm/LlmConnector.php` – `chat()`, `complete()`, `json()` (structured output dle JSON schématu), `models()`, `isAvailable()`; chyby `LlmException` |
| ISBN / SPN | `app/Support/Isbn.php` – `normalize()`, `isValid()` (kontrolní číslice), `normalizeSpn()` |
| Knihovny | `app/Services/LibraryService.php` + `app/Services/LibraryServices/*Connector.php` |
| DTO | `app/DTO/BookData.php` (výsledek knihoven), `BookScanData.php` (výsledek LLM + `identifier()` = ISBN ?? SPN), `LlmResponse.php` |
| DI / singletony | `app/Providers/AppServiceProvider.php` – `ImageManager`, `ImageAnnotatorClient`, `LlmConnector`, binding `BookScanServiceInterface`, registrace observeru |
| Admin UI | `app/Filament/Resources/BookResource.php` (create = rychlý sběr na mobilu, edit = split-screen kontrola), `UserResource.php` |
| Dashboard widgety | `app/Filament/Widgets/` – `AddBook`, `StartReview` (otevře další knihu ve stavu `review`), `BookStats` |
| Frontend pro běžné uživatele | `routes/web.php`, `app/Http/Controllers/BookController.php`, `resources/views/books/*` – `/my-books` (výpis, přidání knihy), vlastní login/registrace |
| Artisan příkazy | `app:run-ocr {id?}` – čtení tiráže (aktuální driver) u knih bez `ocr_full_text`; `app:run-regex` – znovu pustí regex nad uloženým OCR textem (jen Google výstup); `app:llm-scan {id?*} {--save}` – přečte tiráže přes LLM a porovná s údaji v DB (bez `--save` nic neukládá) |

### Datový model `books`

`title`, `author`, `isbn` (ISBN nebo SPN kód), `year` (string), `publisher`,
`classification` (1–5: jako nová … torzo), `bin_number` (číslo přepravky, povinné),
`main_photo` (tiráž – jediná fotka posílaná do OCR), `photos` (JSON pole ostatních fotek),
`status` (`new` → `review` → `done`), `is_antique` (kniha nemá ISBN, typicky před r. 1989),
`ocr_full_text`, `user_id` (vlastník knihy), `note`, `minPrice`, `maxPrice` (camelCase sloupce!).

Fotky leží na disku `public` v `book-scans/`, originály v `book-scans/original/`.

### Knihovní konektory

Všechny implementují `LibraryConnectorInterface::fetch(string $isbn): ?BookData`.
`LibraryService::searchByIsbn()` je volá v pořadí a **vrací první nenulový výsledek** (žádné slučování):

1. `KnihovnyCzConnector` – knihovny.cz API v1 (JSON), vyžaduje title i autora
2. `OpenLibraryConnector` – openlibrary.org `api/books`
3. `GoogleBooksConnector` – Google Books API (`GOOGLE_BOOKS_API_KEY`)
4. `NkpConnector` – NKP Aleph SRU, MARC21 XML (100a autor, 245ab název, 260/264 b,c)

Nový zdroj = nová třída implementující interface + přidat do konstruktoru a `getConnectors()` v `LibraryService`.
`LibraryService::processBook()` data z knihovny přednostně použije, ale null hodnoty nepřepíší už známé údaje (např. z LLM).
V editaci knihy je u pole ISBN akce „Hledat v NKP“, která ve skutečnosti volá celý `searchByIsbn()`.

### LLM (Ollama)

- `LlmConnector` je obecný – používej ho i na další úlohy (ne jen knihy). Obrázky se předávají jako binární
  string, connector je zakóduje do base64. `json()` posílá JSON schéma do `format` (structured outputs) a `temperature: 0`.
- `BookLlmService`:
  - fotku zmenší na `BOOK_SCAN_LLM_IMAGE_SIZE` (1280 px) – originál 2016×4032 trvá 2–4 min, 1280 px ~3–15 s,
  - schéma má jako **první pole `text`** (doslovný přepis) – model nejdřív přepisuje, pak vytěžuje → méně vymýšlení,
  - ISBN/SPN se přijmou jen, pokud jejich číslice **opravdu jsou v přepisu** (malý model jinak opisuje příklady z promptu
    nebo si kód vymyslí) → do promptu **nedávat konkrétní příklady kódů**,
  - kontrolní číslice ISBN se **nevyžaduje** – část starších českých knih má ISBN vytištěné s chybnou číslicí
    (např. `80-237-6907-0`) a knihovny je tak evidují; neplatná se jen loguje,
  - doplňuje jen prázdná pole knihy, do `ocr_full_text` uloží přepis + `--- LLM ---` + vytěžený JSON.
- Ověřené chování na testovacích knihách: ISBN/SPN správně u všech fotek, kde jsou vidět. Název/autor jen pokud
  jsou na tiráži (často nejsou – doplní je pak knihovny podle ISBN). Model občas zkrátí nakladatele.
- Pozor: některé knihy v DB (`done`) mají chybné údaje (kniha 5 má rok originálu 1968, správně 1985), při
  porovnávání přes `app:llm-scan` to není nutně chyba LLM.

## Konfigurace

- `config/services.php` → `services.google.vision_credentials` (`GOOGLE_APPLICATION_CREDENTIALS`, cesta relativní k `base_path`, soubor `google-auth.json` v rootu – **nesmí do gitu**, je v `.gitignore`) a `services.google.books_api_key`.
- `services.llm.*`: `LLM_BASE_URL` (Sail: `http://host.docker.internal:11434`, z WSL hostu `http://127.0.0.1:11434`,
  VPS přes VPN: `http://10.7.0.2:11434`), `LLM_MODEL` (výchozí textový), `LLM_VISION_MODEL`, `LLM_TIMEOUT` (s), `LLM_KEEP_ALIVE`.
- `services.book_scan.*`: `BOOK_SCAN_DRIVER` = `google` (výchozí) | `llm`, `BOOK_SCAN_LLM_IMAGE_SIZE`.
- Při `llm` driveru nastav `DB_QUEUE_RETRY_AFTER` > 600 (timeout `ProcessBookJob`), jinak se dlouhý job může spustit znovu.
- Akce „Spustit OCR“ v editaci knihy běží synchronně v HTTP požadavku (zvedá `set_time_limit`); nginx/php-fpm timeouty
  mohou u pomalého LLM requestu stále zasáhnout.
- Mimo `local` prostředí se vynucuje HTTPS (`URL::forceScheme`).
- `routes/console.php` plánuje `queue:work --stop-when-empty` každou minutu (fallback pro hosting bez workeru).

## Vývoj

```bash
./vendor/bin/sail up -d                    # MySQL, Meilisearch, Mailpit
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan queue:work       # bez workeru se OCR nespustí (QUEUE_CONNECTION=database)
./vendor/bin/sail test                     # PHPUnit 11
./vendor/bin/sail artisan make:filament-user
./vendor/bin/sail artisan app:llm-scan 4 7 8          # test LLM čtení tiráží proti DB (vyžaduje běžící Ollamu)
composer dev                               # alternativa bez Sailu: serve + queue + pail + vite
vendor/bin/pint                            # code style – vlastní pint.json
vendor/bin/phpstan analyse                 # Larastan, level 5, jen app/
```

Code style (`pint.json`): Allman – otevírací závorky tříd, metod **i řídicích struktur** na novém řádku,
`else`/`catch` na novém řádku, mezery kolem `.` při konkatenaci. Drž se toho i v ručně psaném kódu.
Pozor: Pint (Laravel preset) mění `!$x` na `! $x`, kód ale používá `!$x` (PhpStorm) – nepouštěj Pint na celé soubory naslepo.
`tests/Unit/LibraryServiceTest.php` a 2 chyby PHPStanu v `KnihovnyCzConnector` selhávají už na masteru.

## Nasazení

- Produkce: Docker (`Dockerfile.prod`, `compose-prod.yaml`) – `laravel.app` (php-fpm), `laravel.worker`
  (`queue:work`, limit 128 MB; s LLM driverem musí dosáhnout přes VPN na `10.7.0.2:11434`), `nginx` (port 8080, za Nginx Proxy Managerem v síti `proxy_network`), `mysql`.
  Data v bind-volumech `/srv/volume/knihozrout/{db,storage}`.
- GitHub Actions `.github/workflows/deploy.yml`: build image → `ghcr.io/okenagod/knihozrout/laravel-app:latest`
  → scp compose + nginx.conf → `docker compose pull/up` → `migrate --force`, cache příkazy.
- README popisuje i alternativní nasazení na sdílený hosting přes FTP + cron (`.env.vedos`).

## Známé slabiny / na co si dát pozor

- **ISBN regex** (`getRegexIsbn`) je hodně benevolentní (`[0-9Xx\s-]{10,20}`), bere první shodu a nevaliduje
  kontrolní číslici → může chytit telefonní číslo, cenu apod. ISBN se ukládá včetně pomlček.
- U `is_antique` knih se do `isbn` uloží SPN kód a LibraryService ho pak zbytečně hledá jako ISBN.
- `LibraryService::processBook()` data z knihoven přepisuje (null už ne), neukládá zdroj.
- OCR/LLM běží jen nad `main_photo`, ostatní fotky (obálka, hřbet) se neposílají (LLM by je zvládl v jednom požadavku – kandidát na vylepšení).
- `BookOcrService::__destruct()` zavírá singleton `ImageAnnotatorClient` – v dlouho běžícím workeru pozor.
- Akce „Náhled“ v tabulce dělá `array_merge($record->photos, …)` – spadne, když `photos` je null.
- `tests/Unit/LibraryServiceTest.php` je zastaralý (volá `new LibraryService` bez argumentů a sahá na
  neexistující property `connectors`) – po refaktoru na DI neprojde.
- Statusy (`new`/`review`/`done`) a role jsou stringy bez enumu, opakují se ve více souborech.
