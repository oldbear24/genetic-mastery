# Genetic Mastery

Web application that tracks the 54 genetic milestones of an ARK cluster
(9 species × 6 stats, value 250). Players submit claims with screenshots,
admins verify them by hand, and the site computes the shared cluster progress
and the Indo Potential. New milestones are announced on Discord, and the
`/genetic` slash command answers in the server.

- Brief (Czech): [`docs/ZADANI.md`](docs/ZADANI.md)
- Current state, decisions, acceptance criteria → tests: [`docs/STAV.md`](docs/STAV.md)
- Design reference: [`design/README.md`](design/README.md)

## Stack

Laravel 13 on PHP 8.4+, SQLite (WAL, `BEGIN IMMEDIATE`) via Eloquent,
PostgreSQL works too. Blade + Tailwind CSS 4 + Alpine.js (CSP build), fonts
self-hosted. Discord OAuth2 via Socialite (`identify` scope), membership and
roles via the bot (REST), announcements through a database outbox sent by the
scheduler, slash commands via the signed HTTP interactions endpoint. No
separate bot process.

## Local development

Requirements: PHP 8.4+ with `pdo_sqlite`, `gd` (with WebP), `fileinfo`,
`mbstring`, `intl` and `sodium`, Composer 2 and Node 22.

```sh
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

Without a Discord application you can still try everything locally:

```sh
php artisan db:seed --class=DemoSeeder   # sample data from the design (23 of 54)
php artisan dev:login Joe                # player
php artisan dev:login Kuba               # admin
```

`dev:login` prints a signed link valid for 10 minutes. The route behind it
exists only with `APP_ENV=local`.

## Tests

```sh
vendor/bin/pest
```

Tests run on in-memory SQLite (plus a file based SQLite for the concurrent
approval and backup tests) and never call Discord (`Http::fake()`,
`Http::preventStrayRequests()`). On PostgreSQL:

```sh
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_DATABASE=genetic_test \
DB_USERNAME=genetic DB_PASSWORD=genetic vendor/bin/pest
```

GitHub Actions runs the suite on both databases and checks the code style
with Pint (`.github/workflows/tests.yml`).

## Discord setup

1. Create an application with a bot at <https://discord.com/developers/applications>
   and put `DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET`, `DISCORD_BOT_TOKEN`,
   `DISCORD_PUBLIC_KEY`, `DISCORD_GUILD_ID` and `DISCORD_ANNOUNCE_CHANNEL_ID`
   into `.env`. Admins: `ADMIN_ROLE_IDS` and/or `ADMIN_USER_IDS`.
2. OAuth2 redirect URL: `https://<your-site>/auth/discord/callback`.
3. Invite the bot to the server. In the announcement channel it needs View
   Channel, Send Messages and Embed Links. No privileged intents.
4. Interactions Endpoint URL: `https://<your-site>/discord/interactions`.
5. `php artisan discord:register-commands`.

## Docker

```sh
cp .env.example .env   # APP_KEY, APP_URL=https://…, APP_ENV=production, Discord values
docker compose up -d --build
```

- `app`: nginx + PHP-FPM on port 8080, runs migrations and the milestone seed
  on start.
- `scheduler`: `php artisan schedule:work` — Discord outbox every minute,
  backup daily at 03:30, restore test weekly.
- Everything persistent (SQLite database, evidence, logs, backups) lives in
  the `storage` volume.
- Put a TLS-terminating proxy in front (Discord OAuth and interactions need
  HTTPS); `TRUSTED_PROXIES=*` is set in `docker-compose.yml`.
- Logs are JSON lines on stderr with a request ID; secrets are redacted.

## Backups

`php artisan backup:run` writes a consistent database snapshot
(`VACUUM INTO` for SQLite, `pg_dump` for PostgreSQL) and mirrors the evidence
files to `BACKUP_DISK` under `BACKUP_PATH`, keeping snapshots for 30 days.
Use an off-site disk in production (S3 compatible storage needs
`league/flysystem-aws-s3-v3`, or mount a remote volume).

`php artisan backup:verify` restores the newest SQLite snapshot into a scratch
file, runs `PRAGMA integrity_check` and checks that every evidence original
it references is in the mirror. Run it once before going live.

Restore by hand:

1. Stop the app (`docker compose stop app scheduler`).
2. `gunzip -c backups/db/genetic-YYYYMMDD-HHMMSS.sqlite.gz > storage/database/database.sqlite`
   (PostgreSQL: `pg_restore --clean --dbname=… genetic-….pgdump`).
3. Copy `backups/evidence/` back to `storage/app/private/evidence/`.
4. Start the app again.
