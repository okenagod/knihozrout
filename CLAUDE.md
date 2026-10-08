# Knihožrout – digitalizace a evidence archivu knih

Interní systém pro zpracování archivu knih. Uživatel (typicky admin na mobilu) nafotí knihu
(tiráž / ISBN, obálku, hřbet…), zadá číslo přepravky a stav knihy a uloží ji. Na pozadí pak:

1. fotky se převedou na optimalizovaný WebP (originál se zachová),
2. **Google Cloud Vision OCR** přečte text z hlavní fotky (tiráže),
3. z OCR textu se regexem vytáhne ISBN (u starých knih bez ISBN kód typu SPN, např. `14-45-76`),
4. podle ISBN se kniha dohledá v knihovních API a doplní se název, autor, vydavatel, rok,
5. kniha přejde do stavu `review` a admin ji u PC zkontroluje / doplní a označí jako `done`.

Jazyk projektu: UI, komentáře i commity jsou česky. Locale `cs`.

## Stack

- PHP 8.2+ (Sail runtime 8.5, produkční image PHP 8.3-fpm-alpine), **Laravel 12** (README chybně uvádí 11)
- **Filament v3** – admin panel na `/admin` (top navigation, full width), plugin `njxqlus/filament-lightbox`
- `google/cloud-vision` (OCR), `intervention/image` s GD driverem (WebP)
- MySQL 8.4 (Sail i produkce), fronta `database`, Tailwind 4 + Vite pro frontend
- Kód je v adresáři `knihozrout/knihozrout/` (git repo), vnější `knihozrout/` je jen obal.

## Architektura – kde co je

| Oblast | Soubor |
|---|---|
| Model knihy | `app/Models/Book.php` (`$guarded = []`, `photos` cast na array) |
| Uživatelé, role | `app/Models/User.php` – `role` = `admin` / `user`; do Filamentu smí jen `admin` |
| Spuštění pipeline | `app/Observers/BookObserver.php` – `created`: zpracuje fotky + `ProcessBookJob::dispatch()`; `deleted`: smaže fotky i originály |
| Job | `app/Jobs/ProcessBookJob.php` – OCR → `refresh()` → LibraryService |
| Obrázky | `app/Services/ImageProcessingService.php` – originál do `<dir>/original/`, WebP max šířka 1600 px, q80 |
| OCR + parsování | `app/Services/BookOcrService.php` – `processBook()`, `updateRegexText()`, `getRegexIsbn()`, `getTitle()` |
| Knihovny | `app/Services/LibraryService.php` + `app/Services/LibraryServices/*Connector.php` |
| DTO | `app/DTO/BookData.php` – jen `title`, `author`, `publisher`, `year` |
| DI / singletony | `app/Providers/AppServiceProvider.php` – `ImageManager`, `ImageAnnotatorClient`, registrace observeru |
| Admin UI | `app/Filament/Resources/BookResource.php` (create = rychlý sběr na mobilu, edit = split-screen kontrola), `UserResource.php` |
| Dashboard widgety | `app/Filament/Widgets/` – `AddBook`, `StartReview` (otevře další knihu ve stavu `review`), `BookStats` |
| Frontend pro běžné uživatele | `routes/web.php`, `app/Http/Controllers/BookController.php`, `resources/views/books/*` – `/my-books` (výpis, přidání knihy), vlastní login/registrace |
| Artisan příkazy | `app:run-ocr {id?}` – OCR knih bez `ocr_full_text`; `app:run-regex` – znovu pustí regex nad uloženým OCR textem |

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
V editaci knihy je u pole ISBN akce „Hledat v NKP“, která ve skutečnosti volá celý `searchByIsbn()`.

## Konfigurace

- `config/services.php` → `services.google.vision_credentials` (`GOOGLE_APPLICATION_CREDENTIALS`, cesta relativní k `base_path`, soubor `google-auth.json` v rootu – **nesmí do gitu**, je v `.gitignore`) a `services.google.books_api_key`.
- Mimo `local` prostředí se vynucuje HTTPS (`URL::forceScheme`).
- `routes/console.php` plánuje `queue:work --stop-when-empty` každou minutu (fallback pro hosting bez workeru).

## Vývoj

```bash
./vendor/bin/sail up -d                    # MySQL, Meilisearch, Mailpit
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan queue:work       # bez workeru se OCR nespustí (QUEUE_CONNECTION=database)
./vendor/bin/sail test                     # PHPUnit 11
./vendor/bin/sail artisan make:filament-user
composer dev                               # alternativa bez Sailu: serve + queue + pail + vite
vendor/bin/pint                            # code style – vlastní pint.json
vendor/bin/phpstan analyse                 # Larastan, level 5, jen app/
```

Code style (`pint.json`): Allman – otevírací závorky tříd, metod **i řídicích struktur** na novém řádku,
`else`/`catch` na novém řádku, mezery kolem `.` při konkatenaci. Drž se toho i v ručně psaném kódu.

## Nasazení

- Produkce: Docker (`Dockerfile.prod`, `compose-prod.yaml`) – `laravel.app` (php-fpm), `laravel.worker`
  (`queue:work`, limit 128 MB), `nginx` (port 8080, za Nginx Proxy Managerem v síti `proxy_network`), `mysql`.
  Data v bind-volumech `/srv/volume/knihozrout/{db,storage}`.
- GitHub Actions `.github/workflows/deploy.yml`: build image → `ghcr.io/okenagod/knihozrout/laravel-app:latest`
  → scp compose + nginx.conf → `docker compose pull/up` → `migrate --force`, cache příkazy.
- README popisuje i alternativní nasazení na sdílený hosting přes FTP + cron (`.env.vedos`).

## Známé slabiny / na co si dát pozor

- **ISBN regex** (`getRegexIsbn`) je hodně benevolentní (`[0-9Xx\s-]{10,20}`), bere první shodu a nevaliduje
  kontrolní číslici → může chytit telefonní číslo, cenu apod. ISBN se ukládá včetně pomlček.
- U `is_antique` knih se do `isbn` uloží SPN kód a LibraryService ho pak zbytečně hledá jako ISBN.
- `LibraryService::processBook()` data z knihoven **přepisuje** (i null hodnotami), nedoplňuje; neukládá zdroj.
- OCR běží jen nad `main_photo`, ostatní fotky (obálka, hřbet) se do OCR neposílají.
- `BookOcrService::__destruct()` zavírá singleton `ImageAnnotatorClient` – v dlouho běžícím workeru pozor.
- Akce „Náhled“ v tabulce dělá `array_merge($record->photos, …)` – spadne, když `photos` je null.
- `tests/Unit/LibraryServiceTest.php` je zastaralý (volá `new LibraryService` bez argumentů a sahá na
  neexistující property `connectors`) – po refaktoru na DI neprojde.
- Statusy (`new`/`review`/`done`) a role jsou stringy bez enumu, opakují se ve více souborech.
