# Genetic Mastery

Webová aplikace pro evidenci 54 genetických milníků ARK clusteru. Hráči posílají žádosti se screenshoty, admin je ručně ověřuje a web počítá společný progres clusteru a Indo Potential. Novinky oznamuje na Discordu.

Závazné zadání: @docs/ZADANI.md

Vzhled: `design/README.md`, obrázky obrazovek v `design/screens/`.

## Rozhodnutí, která platí

- Varianta A ze zadání: jedna aplikace v Laravelu 13 na PHP 8.4 nebo novějším. Žádný samostatný bot ani další služba.
- UI je česky podle kapitoly „Slovník UI“ v zadání. Názvy druhů a statů zůstávají anglicky.
- Kód, komentáře, commity, README a URL jsou anglicky.
- Ověřené žádosti včetně důkazů jsou veřejné i bez přihlášení. Čekající a zamítnuté vidí jen autor a admini.
- U ostatních otevřených otázek ze zadání platí výchozí odpovědi.

## Stack

- Laravel 13, SQLite v režimu WAL přes Eloquent. Všechno musí fungovat i na PostgreSQL a MySQL/MariaDB (nasazení na WEDOS webhosting, viz `docs/NASAZENI-WEDOS.md`).
- Blade, Tailwind CSS a Alpine.js. Žádná SPA.
- Přihlášení: Laravel Socialite a `socialiteproviders/discord`, scope `identify`. Členství a role ověřuj bot tokenem přes REST (Get Guild Member).
- Slash příkazy přes HTTP endpoint `/discord/interactions`, podpis Ed25519 ověřuj přes `sodium_crypto_sign_verify_detached`.
- Oznámení na Discord jdou přes tabulku `discord_outbox` a plánovač, nikdy přímo z requestu.
- Obrázky přes Laravel Storage (disk `local` mimo webroot), zpracování přes Imagick nebo GD.
- Nasazení: Docker s nginx a PHP-FPM. Lokálně stačí `php artisan serve`.

## Čeština v aplikaci

- `APP_LOCALE=cs` a `APP_FALLBACK_LOCALE=cs`. Všechny texty patří do `lang/cs`, žádné řetězce natvrdo v Blade ani v kontrolerech.
- Validační hlášky česky.
- Množná čísla přes `trans_choice`, například `{1} :count milník|[2,4] :count milníky|[5,*] :count milníků`.
- Datum ve tvaru `14. 11. 2026 14:20`, relativní čas přes Carbon s locale `cs`.
- Jména bez rodu: „Objevitel: Joe“, ne „objevil“ ani „objevila“.
- Discord: názvy příkazů zůstávají anglicky (`/genetic overview` atd.), popisy, odpovědi a oznámení jsou česky.

## Jak pracovat

- Postupuj po etapách z kapitoly „Etapy vývoje“, od 1 do 7. Každá etapa končí spustitelnou aplikací a zelenými testy.
- Po každé etapě commitni (Conventional Commits) a aktualizuj `docs/STAV.md`: co je hotové, co chybí, jaká rozhodnutí jsi udělal a co zůstává otevřené. Nové sezení začíná přečtením `docs/STAV.md`.
- Akceptační kritéria ze zadání převeď na automatické testy (Pest nebo PHPUnit). Testy běží v GitHub Actions na SQLite i PostgreSQL.
- Invariant „milník je VERIFIED nejvýš jednou“ hlídá databáze unikátním částečným indexem, ne jen kód. Schválení je jedna transakce.
- Progres a Indo Potential nikdy neukládej, vždy je počítej z dat.
- Discord v testech mockuj (`Http::fake`). Skutečné tokeny k vývoji nepotřebuješ.
- Tajné údaje patří jen do `.env`. Do repozitáře jde jen `.env.example`.
- Když zadání něco neřeší, rozhodni rozumně, zapiš to do `docs/STAV.md` a pokračuj. Ptej se jen u věcí, které nejdou vrátit.
