# Genetic Mastery

Web application that tracks the genetic milestones of an ARK cluster
(species × 6 stats, value 250; admins manage the species, their images and
single switched off stats on `/admin/species`, the default catalogue has
10 species).
Players submit claims with screenshots,
admins verify them by hand, and the site computes the shared cluster progress
and the Indo Potential. New milestones are announced on Discord, and the
`/genetic` slash command answers in the server.

- Brief (Czech): [`docs/ZADANI.md`](docs/ZADANI.md)
- Current state, decisions, acceptance criteria → tests: [`docs/STAV.md`](docs/STAV.md)
- Design reference: [`design/README.md`](design/README.md)

## Stack

Laravel 13 on PHP 8.4+, SQLite (WAL, `BEGIN IMMEDIATE`) via Eloquent;
MySQL / MariaDB and PostgreSQL work too. Blade + Tailwind CSS 4 + Alpine.js (CSP build), fonts
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
php artisan db:seed --class=DemoSeeder   # sample data in the layout of the design
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

GitHub Actions runs the suite on SQLite, PostgreSQL, MySQL 8.4 and
MariaDB 10.11 and checks the code style with Pint (`.github/workflows/ci.yml`).

## Discord setup

1. Create an application with a bot at <https://discord.com/developers/applications>
   and put `DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET`, `DISCORD_BOT_TOKEN`,
   `DISCORD_PUBLIC_KEY`, `DISCORD_GUILD_ID` and `DISCORD_ANNOUNCE_CHANNEL_ID`
   into `.env`. Admins: `ADMIN_ROLE_IDS` and/or `ADMIN_USER_IDS`.
2. OAuth2 redirect URL: `https://<your-site>/auth/discord/callback`.
3. Invite the bot to the server. In the announcement channel it needs View
   Channel, Send Messages and Embed Links. No privileged intents.
4. Interactions Endpoint URL: `https://<your-site>/discord/interactions`.
5. The scheduler registers `/genetic` by itself within a minute and again
   whenever the species catalogue changes. `php artisan discord:register-commands`
   does it right away.

## Docker

CI builds the image after the test matrix passes on `main` (and on `v*` tags),
smoke-tests it and pushes it to GitHub Container Registry:

| Tag | Source |
| --- | --- |
| `ghcr.io/oldbear24/genetic-mastery:latest` | newest `main` |
| `ghcr.io/oldbear24/genetic-mastery:sha-<commit>` | every built commit |
| `ghcr.io/oldbear24/genetic-mastery:1.2.3`, `:1.2` | tag `v1.2.3` |

The server needs only `docker-compose.yml` and `.env`, no checkout:

```sh
mkdir genetic-mastery && cd genetic-mastery
curl -fsSLO https://raw.githubusercontent.com/oldbear24/genetic-mastery/main/docker-compose.yml
curl -fsSL -o .env https://raw.githubusercontent.com/oldbear24/genetic-mastery/main/.env.example
# Fill in .env: APP_KEY, APP_URL=https://…, APP_ENV=production, Discord values.
# APP_KEY: echo "base64:$(openssl rand -base64 32)"
docker compose pull && docker compose up -d
```

If the repository is private, so is the image: log in first with
`docker login ghcr.io -u <github-user>` and a personal access token with the
`read:packages` scope (the `curl` downloads then need `gh` or a token too).
Update with `docker compose pull && docker compose up -d`; pin a version with
`GENETIC_IMAGE=ghcr.io/oldbear24/genetic-mastery:sha-1a2b3c4` in the shell or
in `.env`.

### Coolify

1. New resource → Public Repository → this repo, build pack **Docker Compose**,
   **Docker Compose Location** `/docker-compose.coolify.yml`.
2. Domain of the `app` service: `https://<host>` (no port; the proxy routes to
   container port 80 and terminates TLS, nothing is published on the host).
3. Environment Variables: fill in the required ones (`APP_KEY`, `APP_URL` equal
   to the domain, the Discord values) and `ADMIN_ROLE_IDS` / `ADMIN_USER_IDS`.
4. Deploy. `app` reports healthy once migrations ran and `/up` answers; the
   scheduler starts after that.

Build from a checkout instead of pulling:

```sh
docker compose -f docker-compose.yml -f docker-compose.build.yml up -d --build
```

- `app`: nginx + PHP-FPM on port 8080, runs migrations and the milestone seed
  on start.
- `scheduler`: `php artisan schedule:work` runs `maintenance:tick` every
  minute: Discord outbox, a backup when the newest one is older than a day,
  a restore test weekly. All in-process, no `proc_open` needed.
- Everything persistent (SQLite database, evidence, logs, backups) lives in
  the `storage` volume.
- Put a TLS-terminating proxy in front (Discord OAuth and interactions need
  HTTPS); `TRUSTED_PROXIES=*` is set in `docker-compose.yml`.
- Logs are JSON lines on stderr with a request ID; secrets are redacted.

## Shared hosting (WEDOS)

Hosts without SSH and with a URL-only cron are supported; the Czech guide is
[`docs/NASAZENI-WEDOS.md`](docs/NASAZENI-WEDOS.md). In short:

- `php scripts/build-wedos.php` builds `build/wedos/genetic-mastery/` (the
  app, uploaded next to the web root) and `build/wedos/www/` (front
  controller, `cron.php`, assets).
- `/ops/deploy/{check|migrate|optimize|clear|discord-commands}/{DEPLOY_TOKEN}`
  replaces the shell, `/cron.php?token={CRON_TOKEN}` (or `/ops/cron/{token}`)
  replaces the CLI cron. Without tokens in `.env` these URLs return 404.
- MariaDB is recommended there; the "verified at most once" rule uses stored
  generated columns with a unique index on MySQL / MariaDB.
- `php artisan ops:check` runs the same environment check on hosts with a shell.

## Backups

`php artisan backup:run` writes a consistent database snapshot
(`VACUUM INTO` for SQLite, a dump written in PHP for MySQL / MariaDB, so no
`mysqldump` is needed, `pg_dump` for PostgreSQL) and mirrors the evidence
files and uploaded species images to `BACKUP_DISK` under `BACKUP_PATH`,
keeping snapshots for 30 days.
Use an off-site disk in production (S3 compatible storage needs
`league/flysystem-aws-s3-v3`, or mount a remote volume).

`php artisan backup:verify` checks the newest snapshot: SQLite is restored into
a scratch file (`PRAGMA integrity_check`), a MySQL dump is read back and its
rows counted against the manifest; every evidence original has to be in the
mirror. Run it once before going live.

Restore by hand:

1. Stop the app (`docker compose stop app scheduler`).
2. `gunzip -c backups/db/genetic-YYYYMMDD-HHMMSS.sqlite.gz > storage/database/database.sqlite`
   (MySQL / MariaDB: import `genetic-….sql.gz` with the mysql client, phpMyAdmin
   or Adminer; PostgreSQL: `pg_restore --clean --dbname=… genetic-….pgdump`).
3. Copy `backups/evidence/` and `backups/species/` (uploaded species images)
   back to `storage/app/private/`.
4. Start the app again.
