# Knihovník - Systém pro digitalizaci a evidenci knih

Interní systém pro digitalizaci a evidenci knih postavený na Laravel 11, Filament PHP v3 a Google Cloud Vision AI.

## Klíčové vlastnosti
- **OCR zpracování**: Automatické vytěžování textu z fotek pomocí Google Cloud Vision.
- **Integrace knihoven**: Automatické doplňování metadat (název, autor, vydavatel, rok) z OpenLibrary, Google Books a Národní knihovny ČR (SRU/MARC21).
- **Split-screen layout**: Optimalizované rozhraní pro rychlou kontrolu dat s fixním panelem fotografií.
- **Asynchronní zpracování**: OCR a hledání v knihovnách probíhá na pozadí pomocí front (Queues).

## Nasazení na produkční server (sdílený hosting)

Pokud nasazujete na hosting bez přístupu k terminálu (CLI), postupujte podle těchto kroků:

### 1. Lokální příprava
Před nahráním na server připravte projekt lokálně (ve vašem prostředí Sail/Docker):
```bash
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
Poté nahrajte celou složku projektu včetně adresáře `vendor` na FTP.

### 2. Konfigurace .env
Na serveru upravte soubor `.env`:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `QUEUE_CONNECTION=database` (pokud máte Cron) nebo `sync` (pokud Cron nemáte).
- `GOOGLE_APPLICATION_CREDENTIALS=google-auth.json` (ujistěte se, že soubor je v kořenovém adresáři).
- `GOOGLE_BOOKS_API_KEY=vase_api_key`

### 3. Nastavení Google Cloud služeb

Pro správnou funkčnost OCR a vyhledávání v Google Books je nutné nastavit následující:

#### A) Google Service Account (pro OCR)
1. V Google Cloud Console vytvořte projekt.
2. Povolte **Cloud Vision API**.
3. V sekci "IAM & Admin" -> "Service Accounts" vytvořte nový servisní účet.
4. Vygenerujte pro tento účet klíč ve formátu **JSON**.
5. Tento soubor přejmenujte na `google-auth.json` a nahrajte jej do kořenového adresáře projektu.
6. Cesta k němu musí odpovídat proměnné `GOOGLE_APPLICATION_CREDENTIALS` v `.env`.

#### B) Google Books API Key
1. V Google Cloud Console (ve stejném nebo jiném projektu) povolte **Books API**.
2. V sekci "APIs & Services" -> "Credentials" vytvořte **API Key**.
3. Tento klíč vložte do souboru `.env` k proměnné `GOOGLE_BOOKS_API_KEY`.

### 4. Zpracování front (OCR na pozadí)
Na sdíleném hostingu nemůžete spustit trvalý proces `php artisan queue:work`. Vyřešte to pomocí Cronu:

Nastavte Cron úlohu každou minutu:
`* * * * * /usr/local/bin/php /cesta/k/projektu/artisan schedule:run >> /dev/null 2>&1`

V souboru `routes/console.php` zajistěte spouštění workeru:
```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:work --stop-when-empty')->everyMinute();
```

### 5. Inicializace (Migrace a Storage)
Protože nemáte terminál, můžete tyto příkazy spustit dočasným přidáním cest do `routes/web.php`:

```php
// Spuštění migrací
Route::get('/init-migrate', function () {
    Artisan::call('migrate', ['--force' => true]);
    return "Migrace hotovy!";
});

// Vytvoření storage linku
Route::get('/init-storage', function () {
    Artisan::call('storage:link');
    return "Storage link vytvořen!";
});
```
Po navštívení těchto adres v prohlížeči tyto routy z bezpečnostních důvodů **ihned odstraňte**.

### 6. Struktura složek
Pro maximální bezpečnost doporučujeme nahrát obsah složky `public` do `public_html` (nebo ekvivalentu vašeho hostingu) a zbytek projektu o úroveň výš. Nezapomeňte pak upravit cesty v `index.php`.

### 7. Optimalizace výkonu
Na produkci je důležité mít nacachovanou konfiguraci a routy:
- `php artisan config:cache`
- `php artisan route:cache`
- `php artisan view:cache`
- `php artisan filament:cache-components` (pro Filament v3)

### 8. Bezpečnostní upozornění
- Nikdy nenechávejte `APP_DEBUG=true` na produkčním serveru.
- Ujistěte se, že soubor `.env` není přístupný z prohlížeče.
- Pravidelně zálohujte databázi i složku `storage/app/public`.

## Vývoj
- Spuštění vývojového prostředí: `./vendor/bin/sail up`
- Spuštění testů: `./vendor/bin/sail test`
- Vytvoření administrátorského účtu: `./vendor/bin/sail artisan make:filament-user`

V produkčním prostředí bez Sailu použijte standardní: `php artisan make:filament-user` (pokud máte přístup k terminálu).
