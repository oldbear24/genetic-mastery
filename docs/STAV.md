# Stav vývoje

Nové sezení začni přečtením tohoto souboru. Zadání je v `docs/ZADANI.md`, pravidla práce v `CLAUDE.md`.

## Přehled etap

| Etapa | Stav |
| --- | --- |
| 1. Kostra | hotovo |
| 2. Přihlášení a hlavní stránka | čeká |
| 3. Submission a upload | čeká |
| 4. Admin rozhodování | čeká |
| 5. Opravy a historie | čeká |
| 6. Discord | čeká |
| 7. Zabezpečení a provoz | čeká |

## Etapa 1: Kostra

**Hotovo**

- Laravel 13.42 na PHP 8.4+ (lokálně ověřeno na PHP 8.5.6), Pest 4.
- `config/genetic.php`: druhy, staty, požadovaná hodnota, vzorec Indo Potential, admin role a ID, `ALLOW_SELF_APPROVE`, limity uploadu a rate limity. Hodnoty jdou přepsat přes `.env`.
- Migrace `users` (Discord ID jako text, jména, avatar, příznaky admin a člen, čas kontroly rolí) a `milestones` (bez sloupce se stavem).
- `MilestoneSeeder` založí 54 milníků z konfigurace. Je idempotentní, nic nemaže.
- SQLite: WAL, `busy_timeout` 5 s a `transaction_mode = IMMEDIATE` (transakce začíná `BEGIN IMMEDIATE`).
- Čeština: `APP_LOCALE=cs`, `lang/cs` (validace, auth, stránkování, `app.php`), Carbon s locale `cs`. Anglické jazykové soubory jsou odstraněné.
- Relace 7 dní, cookie `HttpOnly`, `SameSite=Lax`, `Secure` v produkci.
- Docker: vícestupňový `Dockerfile` (assets, vendor, runtime `php:8.4-fpm-alpine` s nginx a PHP-FPM pod supervisord), `docker-compose.yml` s kontejnery `app` a `scheduler`, limity PHP a nginx pro upload.
- CI: `.github/workflows/tests.yml`, matice SQLite a PostgreSQL 17, kontrola stylu přes Pint.
- Testy: seed (54 milníků, unikátní kódy, 9 × 6, hodnota 250, pořadí, idempotence), konfigurace (čeština, UTC, výchozí pravidla, SQLite IMMEDIATE a WAL, relace), health check.

**Rozhodnutí**

- Testovací framework je Pest (CLAUDE.md dovoluje Pest i PHPUnit).
- Seed páruje milníky podle dvojice druh a stat. Když se změní požadovaná hodnota v konfiguraci, existující řádky dostanou nový kód a hodnotu, nové řádky nevzniknou.
- Názvy druhů a statů jsou v `config/genetic.php`, ne v `lang/cs`: jde o herní názvy, které se nepřekládají.
- Limity PHP v Dockeru jsou o něco vyšší než limity aplikace (20 MB na soubor, 80 MB na request, 20 souborů). Soubor nad limit nebo 11. obrázek tak odmítne aplikace s českou hláškou, ne PHP. Kdyby PHP 11. soubor tiše zahodilo, aplikace by o něm nevěděla.
- Obrázky zpracovává GD (Imagick není v lokálním PHP ani v obrazu nutný).
- `composer.json` vyžaduje `ext-sodium`, `ext-gd`, `ext-fileinfo`, `ext-pdo`.

**Lokální prostředí (poznámka pro vývoj na Windows)**

- V `php.ini` lokálního PHP nejsou zapnutá rozšíření `sodium` a `pdo_pgsql`. Testy proto spouštěj přes `php -d extension=sodium vendor/bin/pest`, nebo rozšíření zapni v `php.ini`.
- Composer není nainstalovaný globálně.
- PostgreSQL lokálně neběží. Sada na PostgreSQL se ověřuje v GitHub Actions.

**Chybí nebo zůstává otevřené**

- Docker obraz nebyl lokálně sestaven (Docker na vývojovém stroji není). Ověří se při prvním nasazení.
- Repozitář zatím nemá GitHub remote, takže CI ještě neběželo.
