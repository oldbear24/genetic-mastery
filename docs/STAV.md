# Stav vývoje

Nové sezení začni přečtením tohoto souboru. Zadání je v `docs/ZADANI.md`, pravidla práce v `CLAUDE.md`.

## Přehled etap

| Etapa | Stav |
| --- | --- |
| 1. Kostra | hotovo |
| 2. Přihlášení a hlavní stránka | hotovo |
| 3. Submission a upload | hotovo |
| 4. Admin rozhodování | hotovo |
| 5. Opravy a historie | hotovo |
| 6. Discord | hotovo |
| 7. Zabezpečení a provoz | hotovo |

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
- CI: `.github/workflows/ci.yml`, matice SQLite a PostgreSQL 17, kontrola stylu přes Pint.
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

## Etapa 3: Submission a upload

**Hotovo**

- Tabulka `submission_images` (UUID `storage_key`, původní jméno jen jako údaj, MIME podle obsahu, rozměry, SHA-256, pořadí, typ `stats` / `ancestry` / `other`).
- `EvidenceProcessor`: typ podle obsahu (finfo i hlavička obrázku se musí shodovat), limit 15 MB na soubor, rozměry z hlavičky před dekódováním a odmítnutí nad 40 Mpx, originál na disk `local` pod UUID, kopie v plném rozlišení přegenerovaná přes GD (bez EXIF, GPS a textových chunků) a náhled WEBP široký 640 px. Vše přes Laravel Storage.
- Formulář nové žádosti podle návrhu: druh, stat se stavem (ověřené a vlastní čekající nejdou vybrat), shrnutí kódu milníku, přetažení nebo výběr 1 až 10 screenshotů s náhledem a typem, poznámka s počítadlem do 1000 znaků, ochrana proti dvojímu odeslání. Předvýběr přes `?milestone=KOD`.
- `SubmissionService`: kontroly SUB-5 (milník není ověřený, hráč nemá jiný čekající claim) před uložením i znovu v transakci se zámkem řádku milníku, limit 10 žádostí za hodinu a 30 za den (počítá se z uložených žádostí), úklid souborů, když se zápis do DB nepovede. Stažení žádosti jako podmíněný update (`WHERE status = 'pending'`).
- `EvidenceController` na `/evidence/{uuid}/{display|thumb|original}`: kopie ověřených žádostí veřejně, ostatní autor a admin, originál jen admin jako příloha. Hlavičky `Content-Type`, `X-Content-Type-Options: nosniff`, `Cache-Control: private`, vlastní CSP se `sandbox`.
- `SubmissionPolicy` (zobrazit, stáhnout, originál). Detail žádosti `/submissions/S-000123` s potvrzením po odeslání, důkazy s lupou, důvodem zamítnutí a tlačítkem Stáhnout.
- Přehled hráče podle návrhu: přivítání se shrnutím, 4 karty (progres, Indo Potential, vlastní objevy, pořadí v žebříčku se shodou), posledních 5 žádostí s akcemi, mřížka s vrstvou „moje“ (Vy, objevitel, Vaše žádost, Čeká + Zažádat, Zamítnuto + Znovu, Zamčeno + Zažádat). Seznam všech vlastních žádostí `/submissions`.
- Detail ověřeného milníku teď ukazuje i důkazy.
- Vývojové přihlášení bez Discordu: `php artisan dev:login Joe` vypíše podepsaný odkaz platný 10 minut. Route existuje jen při `APP_ENV=local`, test hlídá, že jinde není.
- Testy (101): upload a uložení, PNG, JPEG i WEBP, odstranění EXIF a PNG textu, náhled 640 px, odmítnutí PHP souboru přejmenovaného na .png, SVG (i jako .png), GIF (i jako .png), souboru nad 15 MB, 11. obrázku, obrázku nad 40 Mpx a víc než 60 MB celkem, poznámka do 1000 znaků, SUB-5, rate limity, žádná úprava odeslané žádosti, přístup k obrázkům (veřejné ověřené, cizí čekající 403 i bez přihlášení, nosniff, originál jen admin), stažení žádosti, obrázky po změně stavu zůstávají, přehled a vrstva „moje“, formulář.

**Rozhodnutí**

- Kopie pro zobrazení má stejný formát jako originál (PNG zůstane bezeztrátový, aby čísla statů byla čitelná). Náhled je vždy WEBP.
- Orientace z EXIF se při přegenerování neaplikuje (lokální PHP nemá rozšíření `exif`, screenshoty ze hry ji nemají).
- Rate limit SEC-1 se počítá z tabulky `submissions`, ne z cache. Neúspěšné pokusy (chybný soubor) se do limitu nepočítají.
- Hráč ve svém přehledu vidí, kolik dalších žádostí čeká na stejný milník (návrh to ukazuje), nikdy ale kdo.
- „Zamítnutá žádost k opravě“ je zamítnutí, které je u milníku poslední vlastní žádostí a milník není ověřený.
- Typ screenshotu jde vybrat jen se zapnutým JavaScriptem. Bez něj se uloží jako „Jiné“.
- Bezpečnostní CSP celé aplikace přijde v etapě 7. Náhledy v prohlížeči používají `blob:` URL, CSP ho musí povolit v `img-src`.

**Opravená chyba**

- `Auth::login()` čte heslo uživatele. Model `User` teď vrací prázdné heslo (přihlášení je jen přes Discord), jinak by strict mode u uživatele načteného z databáze spadl. Pokrývá to regresní test.

**Chybí nebo zůstává otevřené**

- Vizuální kontrola formuláře a přehledu v prohlížeči neproběhla (rozšíření prohlížeče se během etapy odpojilo). Stránky jsou ověřené testy a HTTP smoke testem včetně odeslání skutečných souborů.

## Etapa 4: Admin rozhodování

**Hotovo**

- Tabulky `audit_log` (jen přidávání: model odmítne update i delete, navíc triggery v SQLite i PostgreSQL), `submission_notes` (veřejné a interní poznámky) a `discord_outbox` (fronta zpráv, odesílání přijde v etapě 6).
- `ReviewService::approve` jako jedna transakce: zámek řádku milníku a žádosti, kontrola stavu, VERIFIED se jménem admina a časem, ostatní čekající žádosti SUPERSEDED s `superseded_by_id`, audit každé změny, volitelná veřejná poznámka a zpráva do `discord_outbox`. Porušení unikátního indexu se převede na srozumitelnou chybu.
- `ReviewService::reject` s povinným důvodem (ADM-6) a auditem.
- ADM-3: kontrola souběžných žádostí s pořadím, pozdější žádost jde schválit jen se zaškrtnutým potvrzením.
- ADM-4: upozornění na stejný SHA-256 v jiné žádosti s odkazem na ni.
- ADM-7: politika `decide` zakáže rozhodnout o vlastní žádosti, pokud `ALLOW_SELF_APPROVE=false`. Platí na serveru (403) i v UI.
- Admin obrazovka podle návrhu `/admin`: fronta nejstarší nahoře s filtrem (každé slovo musí sedět na druh, stat, hráče nebo ID), detail s hráčem, Discord ID, historií hráče, poznámkou, důkazy s lupou a odkazy na originály, kontrolami a formuláři Schválit a Zamítnout. Po rozhodnutí se vrátí do fronty se shrnutím (nový progres a Indo Potential).
- Admin routy chrání `can:admin`, takže hráč dostane 403 a po odebrání role na Discordu do 10 minut také.
- Testy (118), mimo jiné akceptační kritéria: dva admini současně (dva skutečné PHP procesy nad souborovou SQLite, právě jeden VERIFIED, druhý dostane chybu, progres +1), zákaz vlastní žádosti při `ALLOW_SELF_APPROVE=false`, 403 na admin adresách, schválení bez jediného volání Discordu, audit nejde změnit ani smazat. Souběžný test při přepnutí na `DEFERRED` transakce spadne na „database is locked“, takže opravdu hlídá `BEGIN IMMEDIATE`.

**Rozhodnutí**

- Payload v `discord_outbox` nese čísla v okamžiku schválení (V, Indo Potential). Text zprávy se sestaví až při odeslání, aby šel změnit bez úprav uložených řádků.
- Potvrzení pozdější žádosti je zaškrtávací pole nad tlačítkem „Schválit i tak“. Kontrola platí i na serveru.
- Filtr fronty je jedno vyhledávací pole jako v návrhu. Hledá v názvech i kódech druhů a statů, ve jméně hráče (aktuálním i z doby odeslání) a v ID žádosti.
- Souběžný test běží jen na SQLite (potřebuje souborovou databázi mimo transakci testu). Na PostgreSQL stejný případ pokrývá test, který simuluje stav po cizím commitu, a zámek `SELECT … FOR UPDATE`.
- Triggery pro audit log jsou jediné SQL specifické pro databázi a jsou v migraci rozdělené podle driveru. PostgreSQL varianta se ověří až v CI.

**Chybí nebo zůstává otevřené**

- Seznam všech žádostí, stránka historie změn, zrušení ověření, znovuotevření, změna admina a přidání poznámky (etapa 5). Odkazy „Všechny žádosti“ a „Historie změn“ se v hlavičce zatím nezobrazují.

## Etapa 5: Opravy a historie

**Hotovo**

- `CorrectionService` (každá akce je jedna transakce s auditem):
  - OPR-2 zrušení ověření: VERIFIED → REVOKED s povinným důvodem, žádosti uzavřené kvůli této verifikaci (`superseded_by_id`) se vrátí do PENDING, na Discord se zařadí úprava původní zprávy a oprava s novým progresem. Když oznámení ještě neodešlo, jen se zruší (`cancelled`) a úprava ani oprava se neposílá.
  - OPR-3 znovuotevření: REJECTED nebo REVOKED → PENDING, staré rozhodnutí se smaže ze žádosti (zůstává v auditu). Nejde, když je milník ověřený jinou žádostí nebo když hráč už má u milníku jinou čekající žádost.
  - OPR-4 změna ověřujícího admina (jen na admina).
  - OPR-5 veřejná nebo interní poznámka, prostý text.
- Politiky `correct` (stejné pravidlo jako u rozhodování, tedy ne u vlastní žádosti) a `addNote`.
- `/admin/submissions` (OPR-1): všechny žádosti s filtrem podle stavu, milníku, hráče (jméno nebo Discord ID) a admina, stránkování.
- `/admin/audit` (OPR-7): historie změn s filtrem podle akce, admina a ID žádosti, stará a nová hodnota čitelně česky (stavy, jména adminů místo ID).
- Admin detail žádosti ukazuje u rozhodnutých žádostí panel Opravy a u každé žádosti její historii z auditu.
- Detail milníku: veřejná historie ověřené žádosti (ověřeno, odesláno, oznámeno na Discordu), důkazy a veřejné poznámky (HIS-1). Admin navíc vidí interní poznámky a blok „Dřívější pokusy“ se zamítnutými, zrušenými, staženými a uzavřenými žádostmi a důvody (HIS-2) a odkazy na opravy. Aktuální jméno a jméno z doby odeslání (HIS-3).
- Ochranný test překladů: všech 324 literálních klíčů v kódu a šablonách musí existovat v `lang/cs`.
- Testy (137), mimo jiné akceptační kritérium „zrušení verifikace sníží progres o 1 a Indo Potential o 25, vrátí SUPERSEDED claimy do PENDING, upraví zprávu na Discordu a zapíše audit“ (úprava zprávy je ve frontě, odeslání je etapa 6).

**Rozhodnutí**

- Při znovuotevření se z řádku žádosti smaže staré rozhodnutí (admin, čas, důvod). Úplná historie zůstává v auditu a je vidět v adminu u žádosti.
- Zrušit ověření, znovu otevřít a změnit admina nejde u vlastní žádosti (stejně jako ADM-7). Poznámku může přidat kterýkoli admin.
- Veřejnost vidí historii jen u ověřené žádosti. Předchozí pokusy vidí jen admini (HIS-2).
- Akce v auditu mají kódy s tečkou (`submission.approved`), překladové klíče s podtržítkem (`submission_approved`), protože Laravel bere tečku jako zanoření.

**Opravená chyba**

- Popisky akcí v historii změn se zobrazovaly jako surový klíč. Příčina výše, test překladů to teď hlídá obecně.

## Etapa 6: Discord

**Hotovo**

- `DiscordMessages`: oznámení NOVÝ GENETICKÝ MILNÍK jako embed (druh, stat, hodnota, objevitel, progres, Indo Potential, odkaz na detail), zmínka objevitele s `allowed_mentions` jen na něj (DC-2), úprava původní zprávy na „OVĚŘENÍ ZRUŠENO ~~…~~“ a oprava s novým progresem jako odpověď na původní zprávu (DC-3). Jména hráčů se escapují, aby nešlo podstrčit Markdown ani zmínku.
- `OutboxSender` a příkaz `discord:send-outbox` (plánovač každou minutu, `withoutOverlapping`):
  - řádek si runner bere podmíněným updatem `pending → sending`, takže ho nikdy nepošlou dva,
  - vytvoření zprávy nese `nonce` s `enforce_nonce`, takže opakování po ztracené odpovědi nevytvoří duplicitu (DC-5),
  - chyba vrátí řádek do `pending` s rostoucí pauzou 30 s, 1 min, 2 min … nejvýš hodina, 429 respektuje `retry_after`, po 20 pokusech `failed` (DC-4),
  - `message_id` se uloží do outboxu i do `submissions.discord_message_id`,
  - řádky zaseknuté ve stavu `sending` déle než 5 minut se vrátí do fronty,
  - úprava a oprava čekají, dokud původní oznámení neodejde; když nikdy neodešlo, zruší se. Smazaná původní zpráva (404) skončí jako `failed`.
- Interactions endpoint `POST /discord/interactions` mimo skupinu `web` (bez session, cookies a CSRF), ověření Ed25519 přes `sodium_crypto_sign_verify_detached` (neplatný nebo chybějící podpis 401), PING → PONG, rate limit 120 za minutu.
- `/genetic overview` (progres, Indo Potential, textový progress bar), `/genetic progress [druh]`, `/genetic leaderboard` (top 10), `/genetic milestone <druh> <stat>` (jen volající, příznak 64). Všechny odpovědi mají `allowed_mentions.parse = []`.
- `php artisan discord:register-commands` zaregistruje `/genetic` jako guild příkaz (bulk overwrite) s českými popisy a volbami druhů a statů.
- Detail milníku ukazuje „oznámeno na Discordu“, jakmile oznámení odejde.
- Testy (159), mimo jiné akceptační kritéria: při výpadku Discordu schválení projde a oznámení odejde po obnovení právě jednou, zrušení verifikace upraví zprávu na Discordu, interactions endpoint odpoví na PING, neplatný podpis odmítne 401 a na příkazy odpovídá do 1 s, `/genetic overview` ukazuje V / 54 a 150 + 25 × V (i 1500 při V = 54).

**Rozhodnutí**

- Text zprávy se skládá až při odeslání z čísel uložených při schválení. Oznámení tak ukazuje progres v okamžiku schválení, ne v okamžiku odeslání.
- Oprava po zrušení je odpověď (`message_reference`) na původní oznámení, aby byla v kanálu vidět souvislost.
- Volby slash příkazu se jmenují `druh` a `stat` (česky, jak je v zadání `/genetic progress [druh]`), hodnoty jsou kódy druhů a statů.
- Stáří časového razítka u interakcí se nekontroluje (Discord to nevyžaduje a příkazy jen čtou).
- DM hráči o rozhodnutí (DC-6) je podle zadání až verze 1.1.

**Nasazení Discordu (postup)**

1. V Discord Developer Portal vytvořit aplikaci s botem, do `.env` dát `DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET`, `DISCORD_BOT_TOKEN`, `DISCORD_PUBLIC_KEY`, `DISCORD_GUILD_ID`, `DISCORD_ANNOUNCE_CHANNEL_ID`.
2. OAuth2 redirect: `https://<web>/auth/discord/callback`.
3. Bota pozvat na server s oprávněními View Channel, Send Messages a Embed Links v kanálu oznámení.
4. Interactions Endpoint URL: `https://<web>/discord/interactions` (Discord ho při uložení ověří PINGem a neplatným podpisem).
5. `php artisan discord:register-commands`.

## Etapa 7: Zabezpečení a provoz

**Hotovo**

- `SecurityHeaders` na každé odpovědi (SEC-5): CSP bez `unsafe-inline` a `unsafe-eval` (`default-src 'self'`, obrázky i `blob:` pro náhledy a Discord CDN pro avatary, `frame-ancestors 'none'`, `form-action 'self'`, `object-src 'none'`), HSTS, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, COOP. Důkazy si nechávají vlastní přísnější CSP se `sandbox`. Při `npm run dev` v local prostředí se povolí Vite dev server.
- `AssignRequestId`: každý request má ID (převezme rozumné `X-Request-Id` od proxy), je v každém řádku logu i v odpovědi (NF-5).
- Logy: kanál `json` (JSON na stderr, výchozí v Dockeru) a `json_file`. Všechny kanály mají tap `RedactSecrets`, který z textu i kontextu odstraní bot token, client secret, APP_KEY a heslo DB (SEC-4).
- Rate limity (SEC-1): žádosti 10 za hodinu a 30 za den na hráče, přihlášení 10 za minutu na IP, interactions 120 za minutu na IP.
- `TRUSTED_PROXIES` pro provoz za TLS proxy, v produkci se odkazy generují s `https://`.
- Zálohy: `backup:run` (SQLite `VACUUM INTO` nebo `pg_dump`, gzip, zrcadlo důkazů, retence 30 dní, disk `BACKUP_DISK`), `backup:verify` (obnova do dočasného souboru, `PRAGMA integrity_check`, kontrola, že zrcadlo obsahuje všechny originály). Plánovač: záloha denně ve 3:30, zkouška obnovy každé pondělí. Postup ruční obnovy je v README.
- České stránkování (`lang/cs.json`).
- Kontrola v prohlížeči (headless Chrome přes DevTools Protocol, protože rozšíření Claude in Chrome bylo odpojené): pod přísnou CSP funguje Alpine (přepnutí druhu, výběr statu, upload s náhledy, odmítnutí GIF v prohlížeči, počítadlo poznámky, lupa), konzole bez chyb a porušení CSP, na šířce 390 px bez vodorovného posuvníku. Opravené vizuální vady: přetékající „Zamčeno Zažádat“ v přehledu (akce je teď ikona plus, text je v `aria-label`) a zalomené ID ve frontě admina.
- Testy (174).

**Rozhodnutí**

- HSTS se posílá vždy (prohlížeč ho přes HTTP ignoruje), aby to platilo pro všechny stránky bez ohledu na proxy.
- Zálohy PostgreSQL potřebují v kontejneru `pg_dump` (Docker obraz ho kvůli velikosti nemá, výchozí je SQLite). Automatická zkouška obnovy umí jen SQLite snapshoty.
- Off-site kopie: `BACKUP_DISK` může být S3 kompatibilní disk (vyžaduje balík `league/flysystem-aws-s3-v3`) nebo připojený vzdálený svazek.

## Akceptační kritéria → testy

| Kritérium ze zadání | Test |
| --- | --- |
| Seed založí 54 milníků, unikátní kódy, 9 × 6, hodnota 250 | `tests/Feature/Setup/MilestoneSeedTest.php` |
| Web i `/genetic overview` ukazují V / 54 a 150 + 25 × V, při V = 54 je to 1500 | `Progress/ClusterProgressTest.php`, `Discord/InteractionsTest.php` |
| Dva admini současně: ověřený je právě jeden, druhý dostane chybu, progres +1 | `Admin/ConcurrentApprovalTest.php` (dva procesy), `Admin/ReviewTest.php` |
| Zrušení verifikace: progres −1, Indo −25, SUPERSEDED → PENDING, úprava zprávy na Discordu, audit | `Admin/CorrectionsTest.php`, `Discord/OutboxTest.php` |
| Změna jména na Discordu: historie zůstane, nové jméno, v detailu i původní | `Auth/DiscordLoginTest.php`, `Admin/CorrectionsTest.php` (HIS-3) |
| Upload odmítne PHP jako .png, SVG, GIF, soubor nad limit, 11. obrázek, nad 40 Mpx | `Submissions/SubmitClaimTest.php` |
| Obrázek ověřeného milníku bez přihlášení ano, čekajícího ne (ani jiný hráč), `nosniff` | `Submissions/EvidenceAccessTest.php` |
| Hráč dostane 403 na všech admin adresách, po odebrání role do 10 minut | `Security/SecurityTest.php` (všechny routy `admin.*`), `Auth/MembershipRefreshTest.php`, `Admin/ReviewTest.php` |
| Admin neschválí vlastní claim při `ALLOW_SELF_APPROVE=false` | `Admin/ReviewTest.php` |
| Výpadek Discordu: schválení projde, oznámení odejde po obnovení právě jednou | `Discord/OutboxTest.php` |
| Interactions: PING, neplatný podpis 401, odpověď do 1 s | `Discord/InteractionsTest.php` |
| Celá sada na SQLite i PostgreSQL | `.github/workflows/ci.yml` (lokálně ověřeno jen SQLite) |

## Nasazení na WEDOS a MySQL/MariaDB (po etapě 7)

Požadavek: nasadit na WEDOS s MySQL nebo SQLite. Návod je v `docs/NASAZENI-WEDOS.md`.

**Zjištěno o WEDOS webhostingu** (veřejná phpinfo stránka WEDOS a znalostní báze, 14. 11. 2026): PHP 8.4.27 (volitelně 8.0–8.5), `sodium`, GD s WebP, `pdo_mysql`, `pdo_sqlite` (SQLite 3.40), `exif`, `intl`; NoLimit `memory_limit` 512 MB, upload 256 MB, `max_file_uploads` 20; zakázané `exec`, `proc_open`, `putenv`, `symlink`; `open_basedir` na adresář hostingu; Apache s `.htaccess`; MariaDB 10.11; cron jen jako volání PHP skriptu přes URL (základ 3 úlohy a 1× za hodinu, CRON+ 10 úloh a 5 minut); SSH nemá.

**Hotovo**

- MySQL a MariaDB: pravidla „jedna VERIFIED na milník“ a „jedna PENDING na hráče a milník“ přes uložené generované sloupce (`verified_milestone_key`, `pending_milestone_key`, `pending_user_key`) s unikátními indexy. SQLite a PostgreSQL dál používají částečné indexy. Triggery audit logu pro MySQL vzniknou, jen když má uživatel právo TRIGGER, jinak migrace pokračuje a audit chrání model. Spojení v UTC a InnoDB.
- CI: matice SQLite, PostgreSQL 17, MySQL 8.4 a MariaDB 10.11.
- `Maintenance::tick()` a `maintenance:tick`: outbox, záloha podle stáří posledního snapshotu (`BACKUP_EVERY_HOURS`), zkouška obnovy jednou za 7 dní, vše v jednom procesu se zámkem. Plánovač volá `Schedule::call` místo `Schedule::command`, takže nepotřebuje `proc_open`.
- Provozní endpointy mimo skupinu `web` (`routes/ops.php`), chráněné tokeny z `.env` (bez tokenu 404, rate limit): `/ops/cron/{CRON_TOKEN}` a `/ops/deploy/{check|migrate|optimize|clear|discord-commands}/{DEPLOY_TOKEN}`, `POST /ops/deploy/upload-test/{DEPLOY_TOKEN}`. `migrate` založí i prázdný SQLite soubor.
- `EnvironmentCheck` (a `php artisan ops:check`): PHP a rozšíření, GD formáty, databáze a migrace, zapisovatelné adresáře, build frontendu, limity uploadu a paměti, `proc_open`, `open_basedir`, `.env` (bez hodnot tajných údajů), bot token proti Discordu.
- Zálohy: MySQL a MariaDB dump v PHP (schéma, data bez generovaných sloupců, triggery bez DEFINER), manifest u každé zálohy, zkouška obnovy pro SQLite (obnova a `integrity_check`) i MySQL (počty řádků proti manifestu). Dočasné soubory v `storage/app/tmp` kvůli `open_basedir`.
- `RelocateUnreadableUploads`: když PHP nesmí číst dočasný soubor uploadu (`open_basedir`), přesune ho do `storage/app/tmp` a po requestu smaže.
- Relativní `DB_DATABASE` pro SQLite se počítá od kořene aplikace. Rate limity jedou přes souborovou cache (fungují i před první migrací).
- `scripts/build-wedos.php`: balíček `build/wedos/genetic-mastery/` (aplikace bez dev závislostí, bez `.env` a dat) a `build/wedos/www/` (`index.php` s `usePublicPath`, `cron.php`, `.htaccess`, assets). Šablona `.env` pro WEDOS v `deploy/wedos/env.example`.
- Volba `DISCORD_SEND_AFTER_RESPONSE` (odeslání outboxu po odpovědi adminovi), ve výchozím stavu vypnutá.
- Ověřeno lokální simulací rozložení WEDOS (aplikace vedle web rootu, produkční režim, SQLite): kontrola prostředí, první migrace přes URL, stránka s CSS z `www/build`, `.env` nedostupný z webu, cron přes `/ops/cron` i `cron.php` (včetně první zálohy a zkoušky obnovy), `optimize`, JSON log.
- Testy: 187 (+2 MySQL testy zálohy, které běží jen v CI).

**Rozhodnutí**

- Na webhostingu doporučuji MariaDB. SQLite ve WAL na síťovém úložišti sdíleného hostingu není bezpečné, alternativa s `DB_JOURNAL_MODE=delete` je v šabloně, ale bez záruky.
- Vlastní `cron.php` ve web rootu, protože WEDOS cron volá „PHP skript přes URL“ a není jisté, jestli projde hezká URL přes `.htaccess`.
- Zálohy se řídí stářím posledního snapshotu, ne časem v kalendáři: cron přes URL nezaručí přesný čas spuštění.
- `DISCORD_SEND_AFTER_RESPONSE` je vypnuté kvůli pravidlu z `CLAUDE.md` a DC-4 („nikdy přímo z requestu“). Bez doplňku CRON+ tak oznámení může čekat až hodinu. **Otevřená otázka pro vlastníka:** zapnout odeslání po odpovědi, nebo si přikoupit CRON+?
- `CLAUDE.md` doplněno: vše musí fungovat i na MySQL/MariaDB.

## GitHub a Docker obraz (15. 11. 2026)

**Hotovo**

- Veřejný repozitář `github.com/oldbear24/genetic-mastery` (viditelnost rozhodl vlastník).
- Workflow `.github/workflows/ci.yml` (dřív `tests.yml`): po zelené matici testů na `main`, na tagu `v*` nebo při ručním spuštění sestaví obraz, spustí ho (migrace, seed, `/up` a hlavní stránka) a nahraje do `ghcr.io/oldbear24/genetic-mastery`. Tagy `latest` (main), `sha-<commit>`, u tagu `v1.2.3` i `1.2.3` a `1.2`. Cache vrstev přes GitHub Actions.
- `docker-compose.yml` bere obraz z GHCR (`GENETIC_IMAGE` přepíše tag), server tak potřebuje jen tento soubor a `.env`. Sestavení ze zdrojáků přes `docker-compose.build.yml`.

**Rozhodnutí**

- Obraz jen pro `linux/amd64`. Sestavení pro arm64 přes emulaci by kvůli kompilaci rozšíření PHP trvalo mnohem déle. Přidat ho jde jedním řádkem `platforms`, až bude potřeba.
- Obraz se nahraje až po úspěšných testech a smoke testu, nikdy z PR.
- Běh na `main` se nepřerušuje novějším pushem, aby nahrávání obrazu neskončilo napůl.
- Pro Coolify je samostatný `docker-compose.coolify.yml`: bez publikovaných portů (první nasazení padalo na obsazeném portu 8080, provoz vede proxy Coolify), proměnné jako `${VAR}`, aby je Coolify ukázalo v UI, povinné jako `${VAR:?}`. `app` má healthcheck na `/up` a plánovač startuje až po něm. Externí databáze tam zatím není, běží SQLite ve volume.

## Obrázky druhů (15. 11. 2026)

**Hotovo**

- Rendery všech 9 druhů z Dododexu (`dododex.com/media/creature/*.png`) převedené na WebP s průhledností v `public/images/species/{kód}.webp` (např. `rex.webp`, `giga.webp`), celkem asi 220 kB.
- Komponenta `<x-species-art>` a helper `App\Support\SpeciesImage`. Obrázek je dekorativní (prázdný `alt`), leží za obsahem a ocasem mizí přechodem do textu, nohama dolů. Druh bez souboru se zobrazí bez obrázku.
- Mřížka milníků: obrázek ve sloupci Druh (desktop) a v hlavičce karty druhu (mobil), takže ho má i dashboard hráče. Hlavní stránka: za počítadlem GENETIC MASTERY líhnoucí se dinosaurus v inkubátoru (`public/images/genetic-mastery.webp`), za INDO POTENTIAL Indominus s Indoraptorem (`public/images/indo-potential.webp`). Oba obrázky dodal vlastník, jsou ořezané na neprůhlednou část a zmenšené.
- `<x-counter-card image="…">` bere URL obrázku, `<x-species-art>` model druhu (`species`) nebo URL (`src`). Varianty `species-art-row` (mřížka, obrázek dole vpravo) a `species-art-card` (počítadla, svisle na střed, slabší přechod dole).

**Rozhodnutí**

- Soubory jsou zrcadlově otočené, aby druhy hleděly doprava (na přání vlastníka). Obrázek se vejde do rámečku celý (`object-contain`), všechny druhy mají stejnou výšku a nic se neořezává.
- Obrázky leží u nás, ne jako odkaz na Dododex: CSP (`img-src 'self'`) zůstává beze změny a web nezávisí na cizím serveru.
- Původní obrázky jsou herní rendery z ARK (Studio Wildcard) převzaté z Dododexu. Pro komunitní web se to běžně toleruje, licence k nim ale není.

## Druhy a staty spravuje admin (15. 11. 2026)

Vlastník změnil katalog: Velociraptor a Rex zůstávají, přibyl Ceratosaurus, Spinosaurus → Deinosuchus, Allosaurus → Tusoteuthis, Yutyrannus → Beelzebufo, Megalosaurus → Basilisk. Zároveň chtěl, aby katalog spravoval admin a u některých druhů šel vypnout stat (např. Oxygen).

**Hotovo**

- Tabulka `species` (kód, název, pořadí, `is_active`) a sloupec `milestones.is_active` (stat zapnutý u druhu). Migrace `2026_11_15_000100_create_species_table` založí výchozí katalog z `config/genetic.php` (10 druhů v pořadí Rex, Raptor, Giganotosaurus, Carnotaurus, Ceratosaurus, Therizinosaurus, Deinosuchus, Tusoteuthis, Beelzebufo, Basilisk). Druhy, které už v databázi mají milníky, ale v konfiguraci nejsou (Spinosaurus, Allosaurus, Yutyrannus, Megalosaurus), vloží jako vypnuté.
- `App\Services\Catalogue`: `sync()` (seeder při každém nasazení: dotvoří chybějící milníky, srovná pořadí a kódy, nic nemaže a nikdy nepřepne zapnuto/vypnuto), `create()`, `update()` (název, aktivní, zapnuté staty), `move()`. Každá změna je jedna transakce se záznamem v audit logu (`species.created`, `species.updated`, `species.moved`, jen změněné hodnoty).
- Stránka `/admin/species` („Druhy a staty“ v admin menu): název, aktivní, šest zaškrtávátek statů, posun nahoru/dolů, počet ověřených a čekajících žádostí u druhu a formulář pro nový druh. Aktivní druh musí mít aspoň jeden stat.
- Počítá se jen aktivní milník (stat zapnutý a druh aktivní): progres, celkový počet, Indo Potential i jeho maximum, žebříček a poslední objevy. Výchozí katalog: 60 milníků, INDO POTENTIAL nejvýš 1650.
- Vypnutý stat ukazuje mřížka jako „— Nepoužívá se“ (veřejná i Můj přehled), formulář žádosti ho nenabízí, server žádost odmítne a schválení čekající žádosti na vypnutém milníku admin nedostane (zamítnout jde). Detail vypnutého milníku zůstává dostupný s upozorněním. Vypnutý druh z mřížky zmizí.
- Discord: nabídka druhů v `/genetic` se bere z aktivních druhů (nejvýš 25). `CommandSync` v plánovači pozná změnu definice podle hashe a příkaz znovu zaregistruje, po chybě čeká 15 minut. `discord:register-commands` hash uloží taky. `/genetic milestone` u vypnutého milníku odpoví, že se nepoužívá, `/genetic progress <druh>` vypnuté staty vynechá.
- Obrázky nových druhů z Dododexu (`cerato`, `deino`, `tuso`, `beelze`, `basilisk`), otočené doprava. Obrázky původních druhů zůstávají pro případ, že je admin znovu zapne.
- Zálohy: tabulka `species` je v manifestu počtů řádků. Kontrola nasazení (`/ops/deploy/check`) chce aspoň jeden aktivní milník místo přesně 54.
- Testy: nový `Admin/SpeciesTest` (vypnutí statu i druhu a zpětné zapnutí, odmítnutí žádosti a schválení, přidání druhu, validace kódu, přejmenování, pořadí, audit, registrace Discord příkazů a čekání po chybě), migrace s původními druhy, Discord odpověď u vypnutého milníku. Existující testy přepočítané na 60 a 1650.

**Rozhodnutí**

- Druhy jsou v databázi, staty zůstávají v konfiguraci (šest pevných sloupců mřížky). `config('genetic.species')` je jen výchozí katalog pro migraci, pozdější úpravy konfigurace existující instalaci nemění.
- Nic se nemaže. Vypnutý druh nebo stat drží žádosti i důkazy a po zapnutí se zase počítají. Kód druhu je trvalý (je v kódech milníků a URL), měnit jde jen název.
- Žebříček a poslední objevy počítají jen aktivní milníky, stejně jako progres. Objevitel vypnutého druhu tak o objev v žebříčku přijde, dokud admin druh nezapne.
- Maximum Indo Potential se počítá z aktivních milníků (150 + 25 × počet), takže se po změně katalogu mění (60 milníků = 1650). Pokud má zůstat pevných 1500, je potřeba upravit `INDO_BASE` nebo `INDO_PER_MILESTONE`.
- Výchozí vypnuté staty se nehádají. Dododex staty v HTML neukazuje, takže všechny druhy začínají se všemi šesti a admin vypne, co ve hře nejde (pravděpodobně Oxygen u Tusoteuthise).
- Názvy zůstávají herní („Rex“, „Raptor“), přestože vlastník psal „Tyrannosaurus rex“ a „Velociraptor“. Změnit jde v administraci.

## Obrázek druhu nahrává admin (15. 11. 2026)

Vlastník chtěl, aby admin mohl k novému druhu přidat obrázek.

**Hotovo**

- Na `/admin/species` jde obrázek nahrát při přidání druhu i později u každého druhu, nahradit ho a nahraný obrázek odebrat. Zaškrtávátko „Otočit vodorovně“ obrázek zrcadlí, aby zvíře koukalo doprava jako ostatní.
- `App\Services\SpeciesArt`: stejné kontroly jako u důkazů (`EvidenceProcessor::inspect()`: typ podle obsahu, 15 MB, 40 Mpx), zmenšení na nejvýš 400 px (jako dodané obrázky), převod na WEBP přes GD s průhledností a bez metadat. Soubor leží na disku důkazů jako `species/{KÓD}/{16 hex}.webp`, cesta je ve sloupci `species.image`.
- Veřejná adresa `/species-art/{kód}/{verze}` (`SpeciesArtController`): verze je náhodná část jména souboru, takže se s každým nahráním změní a prohlížeč může obrázek cachovat napořád (`immutable`). Stará adresa po výměně vrací 404. Hlavičky jako u důkazů (`nosniff`, CSP se `sandbox`).
- Pořadí zdrojů (`SpeciesImage::for()`): nahraný obrázek, jinak dodaný `public/images/species/{kód}.webp`, jinak nic. `<x-species-art>` bere model druhu (`species`) nebo URL (`src`), mřížka dostává URL v řádku z `ClusterProgress::grid()`.
- Audit: změna obrázku je v `species.created` a `species.updated` jako „obrázek: nahraný soubor ….webp“.
- Zálohy zrcadlí kromě `evidence/` i `species/`. Obnova tedy kopíruje zpět obě složky.
- Testy: zmenšení a průhlednost (pixely), otočení, veřejné vydání s hlavičkami, výměna a odebrání s návratem k dodanému obrázku, odmítnutí PHP souboru jako .png, GIF a obrázku nad 40 Mpx beze změny druhu, nový druh s obrázkem, úklid souboru při chybě uložení, 404, zrcadlení v záloze.

**Rozhodnutí**

- Soubory jdou na disk důkazů (`storage/app/private`), ne do `public/`: v Dockeru přežijí nové nasazení (volume `storage`) a na WEDOS není potřeba symlink `storage:link`.
- Nahrazený obrázek se nemaže, odkazuje na něj audit log. Je to pár desítek kB.
- Obrázek se jen zmenší a zrcadlí, neořezává se. Nejlépe vypadá PNG nebo WEBP s průhledným pozadím, u fotky s pozadím je vidět obdélník (přechod do textu ho jen zjemní).
- Sloupec `species.image` je přímo v migraci `create_species_table`, protože ta ještě nebyla nasazená.

## Co zůstává otevřené

- **MySQL/MariaDB a PostgreSQL**: sada lokálně běžela jen na SQLite (lokální databázový server nemám povolený). MySQL část (generované sloupce, triggery, PHP dump) ověří až CI.
- **Skutečný WEDOS**: nasazení je ověřené simulací, ne na hostingu. Kontrola `/ops/deploy/check` a test nahrávání ukážou případné rozdíly (hlavně `open_basedir` a dočasné soubory uploadu).
- **PostgreSQL**: první běh CI odhalil dva testy, které čekaly chybu databáze uvnitř transakce testu; PostgreSQL po chybě odmítá další dotazy. Očekávané chyby teď běží v savepointu (`DB::transaction`). Produkční kód to netýká.
- **Docker obraz** se sestavuje v CI a projde smoke testem (Docker na vývojovém stroji není). Nasazení v Coolify zatím neběželo celé.
- **Skutečný Discord**: OAuth, bot a interakce jsou ověřené jen proti mockům. Při prvním nasazení projít postup v části Etapa 6.
- **Verze 1.1**: DM hráči o rozhodnutí (DC-6).
- Otázky ze zadání, u kterých platí výchozí odpověď: souběžné claimy rozhoduje admin (pořadí a potvrzení pozdějšího), self-approve řídí `ALLOW_SELF_APPROVE`, jméno objevitele je přezdívka na serveru, bez sezón, 250 jsou body včetně mutací.
