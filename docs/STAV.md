# Stav vývoje

Nové sezení začni přečtením tohoto souboru. Zadání je v `docs/ZADANI.md`, pravidla práce v `CLAUDE.md`.

## Přehled etap

| Etapa | Stav |
| --- | --- |
| 1. Kostra | hotovo |
| 2. Přihlášení a hlavní stránka | hotovo |
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

**Chybí nebo zůstává otevřené (etapa 1)**

- Docker obraz nebyl lokálně sestaven (Docker na vývojovém stroji není). Ověří se při prvním nasazení.
- Repozitář zatím nemá GitHub remote, takže CI ještě neběželo.

## Etapa 2: Přihlášení a hlavní stránka

**Hotovo**

- Tabulka `submissions` podle zadání. Navíc sloupce `withdrawn_at` a `superseded_by_id` (viz rozhodnutí). Dva unikátní částečné indexy: jedna VERIFIED na milník, jedna PENDING na hráče a milník.
- Enumy `SubmissionStatus` (s povolenými přechody podle stavového diagramu) a `MilestoneState` (odvozený, neukládá se).
- `ClusterProgress`: V, progres, Indo Potential, mřížka, poslední objevy, žebříček a pořadí hráče. Vše se počítá dotazem.
- Přihlášení přes Discord (Socialite, scope jen `identify`, `state`), kontrola členství a rolí botem (Get Guild Member), admin podle `ADMIN_ROLE_IDS` nebo `ADMIN_USER_IDS`. Access ani refresh token se neukládá.
- Middleware `RefreshDiscordMembership`: po 10 minutách znovu ověří členství a role. Kdo opustil server, je odhlášen.
- Hlavní stránka podle návrhu: počítadla, mřížka 9 × 6 (na mobilu karty po druzích), legenda, posledních 5 objevů, top 5 objevitelů. Veřejný žebříček `/leaderboard`, základní detail milníku `/milestones/{code}`, přihlašovací stránka, chybové stránky 403, 404, 419, 429, 500 a 503.
- Vzhled: tokeny z `design/README.md` v Tailwindu, písma Inter, Space Grotesk a JetBrains Mono self-hosted přes `@fontsource` (latin a latin-ext), Alpine.js v CSP buildu.
- Časy: server vypíše `14. 11. 2026 14:20` v `APP_DISPLAY_TIMEZONE`, prohlížeč každý `<time data-local>` přepíše do svého časového pásma. Relativní čas přes Carbon s locale `cs`.
- `DemoSeeder` s ukázkovými daty z návrhu (23 z 54): `php artisan db:seed --class=DemoSeeder`. V produkci se nespustí.
- Testy (62): výpočty pro V = 0, 18, 23, 54 přes službu i web, odvozený stav, DB invarianty, přechody stavů, hlavní stránka (HP-1 až HP-5), žebříček (LB-1), přihlášení (AUTH-1 až AUTH-5, AUTH-7), změna jména, obnova rolí do 10 minut, výpadek Discordu, rate limit přihlášení. Discord je v testech vždy mockovaný, `Http::preventStrayRequests()` zastaví každý nemockovaný request.

**Rozhodnutí**

- Klikatelné jsou všechny buňky mřížky, ne jen ověřené (HP-3 říká „klik na buňku“). Detail zamčeného nebo čekajícího milníku ukáže jen stav, nikdy jméno žadatele (HP-4).
- `superseded_by_id` si pamatuje, kterou verifikací byl claim uzavřen. Zrušení verifikace (OPR-2) pak vrátí do PENDING přesně tyto claimy.
- Datum objevu pro žebříček je čas odeslání ověřené žádosti (`submitted_at`), ne čas schválení. Pomalé schvalování tak nemění pořadí. Poslední objevy na hlavní stránce se řadí podle času ověření.
- Když Discord při obnově rolí neodpovídá, platí poslední známý stav a další pokus je za minutu (NF-2: výpadek nesmí blokovat adminy). Při přihlášení se bez Discordu přihlásit nejde.
- Progress bary jsou SVG, aby nepotřebovaly inline styly. CSP pak nemusí povolovat `unsafe-inline` (etapa 7).
- Alpine.js je v CSP buildu (`@alpinejs/csp`), komponenty se registrují v `resources/js/components.js`.
- Texty vykají. Formulace bez rodu i u adminů („Ověřující admin“, ne „Ověřil“).
- `CLUSTER_NAME` je nepovinný, bez něj hlavička ukazuje jen „ARK cluster“.

**Chybí nebo zůstává otevřené**

- Detail milníku zatím neukazuje důkazy, historii a admin blok (etapa 3 a 5).
- Přehled hráče je zatím jen přivítání a počítadla (etapa 3).
