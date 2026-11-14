# Nasazení na WEDOS

Návod pro WEDOS webhosting (NoLimit, NoLimit Extra) bez SSH. Na WEDOS VPS použijte Docker podle `README.md`.

## Co WEDOS webhosting umí a co z toho plyne

Ověřeno 14. 11. 2026 z veřejné phpinfo stránky WEDOS a znalostní báze:

| Vlastnost | Stav | Co to znamená pro aplikaci |
| --- | --- | --- |
| PHP 8.4 a 8.5 | ano (volí se v administraci) | Nastavte 8.4 nebo 8.5. Změna se projeví do 30 minut. |
| Rozšíření `sodium`, `gd` s WebP, `pdo_mysql`, `pdo_sqlite`, `fileinfo`, `intl`, `exif` | ano | Vše potřebné je k dispozici. |
| Limity NoLimit: `memory_limit` 512 MB, upload 256 MB, `max_file_uploads` 20 | ano | Stačí na 10 obrázků po 15 MB a obrázky do 40 Mpx. |
| `exec`, `proc_open`, `symlink`, `putenv` | zakázané | Plánovač běží v rámci jednoho procesu (`maintenance:tick`), zálohu MariaDB dělá PHP místo `mysqldump`. |
| `open_basedir` na adresář hostingu | ano | Dočasné soubory jdou do `storage/app/tmp`, nahrané soubory se v případě potřeby přesunou tamtéž. |
| SSH | není | Migrace, kontrola prostředí a registrace Discord příkazů se spouští přes zabezpečené URL. |
| CRON | jen volání PHP skriptu přes URL; základ 3 úlohy a nejméně 1× za hodinu, doplněk CRON+ 10 úloh a nejméně 5 minut | Cron volá `cron.php?token=…` a posílá oznámení na Discord, opakování a zálohy. Bez CRON+ může oznámení čekat až hodinu (viz níže). |
| Databáze | MariaDB 10.11 | Doporučená volba. |

### MariaDB, ne SQLite

Na webhostingu doporučuji MariaDB. Webhosting WEDOS běží v kontejnerech nad sdíleným (síťovým) úložištěm. SQLite v režimu WAL na síťovém disku fungovat nemusí a souběžné zápisy z více procesů jsou riskantní. Kdo SQLite přesto chce, nastaví `DB_JOURNAL_MODE=delete` a `DB_SYNCHRONOUS=full` (viz `.env.example`). Na WEDOS VPS s lokálním diskem je SQLite v pořádku.

Pravidlo „milník je ověřený nejvýš jednou“ hlídá i v MariaDB databáze: generované sloupce s unikátním indexem (MariaDB neumí částečné indexy). Souběžné schválení serializuje zámek řádku milníku.

## 1. Příprava v administraci WEDOS

1. **PHP:** Webhosting → Konfigurace PHP → verze 8.4 (nebo 8.5). Ostatní limity nechte výchozí.
2. **Databáze:** založte MariaDB databázi a poznamenejte si server, název, uživatele a heslo.
3. **Doména:** zjistěte, který adresář na FTP patří doméně nebo subdoméně aplikace (hlavní doména typicky `www/`, další domény a subdomény mají vlastní podadresář).
4. **HTTPS:** zapněte certifikát (Let's Encrypt). Discord přihlášení i interactions endpoint vyžadují HTTPS.
5. **CRON+** (doporučeno): cron pak běží každých 5 minut místo každou hodinu, takže oznámení o novém milníku dorazí na Discord nejpozději do 5 minut.

### Oznámení bez CRON+

Podle zadání (DC-4) oznámení odesílá plánovač, nikdy request admina. Se základním cronem to znamená zpoždění až hodinu. Aplikace umí i `DISCORD_SEND_AFTER_RESPONSE=true`: zpráva odejde hned po odeslání odpovědi adminovi. Admin na Discord nečeká a při výpadku zprávu zopakuje cron. Jde ale o odchylku od DC-4, proto je volba ve výchozím stavu vypnutá.

## 2. Discord aplikace

Postup je v `README.md`, část Discord setup. Redirect URL `https://<web>/auth/discord/callback`, Interactions Endpoint URL `https://<web>/discord/interactions`. Interactions URL uložte až po kroku 5, kdy aplikace běží.

## 3. Sestavení balíčku (na svém počítači)

Potřebujete PHP 8.4+, Composer, Node 22 a Git.

```sh
npm ci
npm run build
php scripts/build-wedos.php
```

Vznikne `build/wedos/`:

- `genetic-mastery/` je aplikace (bez `.env`, bez dat). Nahrává se **vedle** webového adresáře, ne do něj.
- `www/` je obsah webového adresáře: `index.php`, `cron.php`, `.htaccess`, `build/` s CSS a JS, favicon.

`index.php` a `cron.php` hledají aplikaci relativně k webovému adresáři, výchozí je `../genetic-mastery`. Když je web v hlubším adresáři, zadejte cestu při sestavení. Například web v `www/domains/genetic.example.cz/` a aplikace v kořeni FTP:

```sh
php scripts/build-wedos.php --web-to-app=../../../genetic-mastery
```

Composer lze zadat cestou: `--composer=C:/nastroje/composer.phar`.

## 4. Nahrání přes FTP

1. `build/wedos/genetic-mastery/` nahrajte do kořene FTP (vedle `www/`).
2. Obsah `build/wedos/www/` nahrajte do webového adresáře domény.
3. V `genetic-mastery/` vytvořte `.env` podle `genetic-mastery/.env.example` a vyplňte:
   - `APP_KEY`: lokálně `php artisan key:generate --show`,
   - `APP_URL=https://…`,
   - údaje k MariaDB (`DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`),
   - Discord údaje a `ADMIN_ROLE_IDS` nebo `ADMIN_USER_IDS`,
   - `CRON_TOKEN` a `DEPLOY_TOKEN`: dva různé náhodné řetězce aspoň po 32 znacích, např. `php -r "echo bin2hex(random_bytes(24));"`.
4. Adresáře `genetic-mastery/storage` a `genetic-mastery/bootstrap/cache` musí být zapisovatelné (na WEDOS jsou).

`.env`, `storage/` ani databáze se z webu nedají otevřít: leží mimo webový adresář a `.htaccess` navíc blokuje skryté soubory.

## 5. První spuštění

Všechny adresy otevřete v prohlížeči (`<D>` je `DEPLOY_TOKEN`):

1. `https://<web>/ops/deploy/check/<D>` vypíše kontrolu prostředí (PHP, rozšíření, databáze, zápis do adresářů, limity, Discord). Řádky `FAIL` opravte. Před migrací je FAIL u migrací v pořádku.
2. `https://<web>/ops/deploy/migrate/<D>` vytvoří tabulky a 54 milníků.
3. Test nahrávání (ověří, že PHP smí číst nahrané soubory):
   ```sh
   curl -F "image=@screenshot.png" https://<web>/ops/deploy/upload-test/<D>
   ```
   Má vrátit `OK upload readable, image/png …`.
4. `https://<web>/ops/deploy/discord-commands/<D>` zaregistruje `/genetic`.
5. `https://<web>/ops/deploy/optimize/<D>` zapne cache konfigurace, rout a šablon.
6. V Discord Developer Portal uložte Interactions Endpoint URL (Discord ho ověří).
7. **Vypněte `DEPLOY_TOKEN`:** smažte jeho hodnotu v `.env` a přes FTP smažte `genetic-mastery/bootstrap/cache/config.php` (konfigurace je po kroku 5 v cache, změna `.env` se jinak neprojeví).

## 6. CRON

V administraci WEDOS → CRON přidejte úlohu s URL skriptu:

```
https://<web>/cron.php?token=<CRON_TOKEN>
```

Interval co nejkratší (1 hodina, s CRON+ 5 minut). Každé spuštění pošle čekající zprávy na Discord, jednou denně udělá zálohu a jednou týdně zkoušku obnovy. Souběžná spuštění hlídá zámek. Ručně spuštěný cron vrátí JSON s výsledkem.

## 7. Aktualizace

1. Lokálně znovu `npm run build` a `php scripts/build-wedos.php`.
2. Nahrajte `genetic-mastery/` a `www/`. **Nepřepisujte ani nemažte** `genetic-mastery/.env` a `genetic-mastery/storage/` (v balíčku nejsou, jen je při synchronizaci nemažte).
3. Dočasně vraťte `DEPLOY_TOKEN` do `.env`, smažte `bootstrap/cache/config.php`, spusťte `…/ops/deploy/migrate/<D>` a `…/ops/deploy/optimize/<D>`, pak token znovu vypněte (krok 5.7).

## 8. Zálohy a obnova

- Zálohy leží v `genetic-mastery/storage/app/private/backups/` (databáze `db/genetic-…sql.gz` s manifestem, zrcadlo důkazů `evidence/`). Uchovávají se 30 dní.
- To je pořád stejný server. Pravidelně je stahujte přes FTP, nebo nastavte `BACKUP_DISK` na vzdálené úložiště (S3 kompatibilní disk potřebuje balík `league/flysystem-aws-s3-v3`).
- **Obnova MariaDB:** soubor `genetic-…sql.gz` rozbalte a importujte přes phpMyAdmin nebo Adminer z administrace WEDOS (dump obsahuje tabulky i data). Adresář `backups/evidence/` zkopírujte zpět do `storage/app/private/evidence/`.
- Triggery, které brání úpravám auditu, potřebují právo TRIGGER. Když ho databázový uživatel nemá, migrace je přeskočí, kontrola prostředí to ukáže jako WARN a audit dál chrání aplikace.

## 9. Když něco nefunguje

- **Chyba 500:** `genetic-mastery/storage/logs/genetic-YYYY-MM-DD.jsonl` (každý řádek má `request_id`, stejné ID je v hlavičce odpovědi `X-Request-Id`).
- **Po změně `.env` se nic nezměnilo:** smažte `bootstrap/cache/config.php`.
- **Nahrávání obrázků selhává:** spusťte test nahrávání z kroku 5.3.
- **Discord odmítá Interactions URL:** zkontrolujte `DISCORD_PUBLIC_KEY` (64 hexadecimálních znaků) a že web běží na HTTPS.
- **Oznámení chodí pozdě:** zkontrolujte CRON úlohu a její interval (bez CRON+ jednou za hodinu), případně `DISCORD_SEND_AFTER_RESPONSE`.
