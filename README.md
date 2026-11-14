# Genetic Mastery

Web application that tracks the 54 genetic milestones of an ARK cluster
(9 species × 6 stats, value 250). Players submit claims with screenshots,
admins verify them by hand, and the site computes the shared cluster progress
and the Indo Potential. New milestones are announced on Discord.

- Brief (Czech): [`docs/ZADANI.md`](docs/ZADANI.md)
- Current state, decisions and open items: [`docs/STAV.md`](docs/STAV.md)
- Design reference: [`design/README.md`](design/README.md)

## Stack

Laravel 13 on PHP 8.4+, SQLite (WAL) via Eloquent (PostgreSQL works too),
Blade + Tailwind CSS + Alpine.js, Discord OAuth2 via Socialite, Discord REST
API and HTTP interactions for slash commands. No separate bot process.

## Local development

Requirements: PHP 8.4+ with `pdo_sqlite`, `gd`, `fileinfo`, `mbstring`,
`intl` and `sodium` (`sodium` is needed for the Discord interactions
endpoint), Composer 2 and Node 22.

```sh
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

Discord credentials go to `.env` only (see `.env.example`). Tests never call
Discord; they fake it with `Http::fake()`.

## Tests

```sh
vendor/bin/pest
```

By default tests run on in-memory SQLite. To run them on PostgreSQL:

```sh
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_DATABASE=genetic_test \
DB_USERNAME=genetic DB_PASSWORD=genetic vendor/bin/pest
```

GitHub Actions runs the suite on both databases (`.github/workflows/tests.yml`).

## Docker

```sh
cp .env.example .env   # set APP_KEY, APP_URL, Discord values, APP_ENV=production
docker compose up -d --build
```

The `app` container serves nginx + PHP-FPM on port 8080 and runs migrations
and the milestone seed on start. The `scheduler` container runs
`php artisan schedule:work`. Everything persistent (SQLite database, uploaded
evidence, logs) lives in the `storage` volume. Put a TLS-terminating proxy in
front: Discord OAuth and the interactions endpoint require HTTPS.
