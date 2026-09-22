# ChurchX

ChurchX is a multi-branch church administration system built with PHP 8.4, Laravel 13, PostgreSQL, Blade, and Backpack CRUD 7. It covers people, attendance, departments, groups, events, finance, assets, broadcasts, reporting, permissions, and audit history.

This project uses only Backpack's free/open-source CRUD functionality and does not require Backpack PRO.

## Requirements

- PHP 8.4 with `pdo_pgsql`, OpenSSL, Mbstring, XML, Ctype, JSON, BCMath, and Fileinfo
- Composer 2
- Node.js 20+ and npm
- PostgreSQL 15+

## Installation

```bash
composer install
copy .env.example .env
php artisan key:generate
npm install
npm run build
php artisan storage:link
php artisan church:setup
```

Create the PostgreSQL database/user first, then set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env`. The setup command migrates, seeds roles and reference data, and securely prompts for church, first branch, and administrator details. The administrator password must contain at least 12 characters and is never displayed.

For non-interactive infrastructure pipelines, run migrations and seeders separately:

```bash
php artisan migrate --force
php artisan db:seed --force
```

Backpack's assets and free Tabler theme are installed through Composer. No license key or PRO package is required.

## Local development

```bash
composer run dev
```

Or run the services independently:

```bash
php artisan serve
php artisan queue:work --tries=3
php artisan schedule:work
npm run dev
```

The admin area is under `/admin`. Public registration is disabled by default. Login supports email/password and phone/PIN with throttling.

## Configuration

- `APP_URL`, `APP_ENV`, `APP_DEBUG`: application environment
- `DB_*`: PostgreSQL connection
- `FILESYSTEM_DISK`: uploaded logos, profile images, flyers, and receipts
- `QUEUE_CONNECTION=database`: broadcast delivery jobs
- `MAIL_*`: Laravel mail transport
- `CHURCH_AUTH_MAX_ATTEMPTS` and `CHURCH_AUTH_DECAY_SECONDS`: login throttling
- `BACKPACK_REGISTRATION_OPEN=false`: keep public admin registration disabled

Church name, logo, currency, and timezone are maintained in Church Settings. Timestamps are stored consistently by Laravel; user-facing timezone configuration comes from the Church record.

SMS and WhatsApp use safe logging provider interfaces until a real provider is configured. Implement `SmsProviderInterface` or `WhatsAppProviderInterface`, bind the provider in `AppServiceProvider`, and keep credentials in environment-backed configuration. Do not put provider secrets in source control.

## Operations

Run queued broadcasts with a supervised worker:

```bash
php artisan queue:work --queue=default --tries=3 --timeout=120
```

Run Laravel's scheduler every minute in production:

```cron
* * * * * cd /path/to/churchx && php artisan schedule:run >> /dev/null 2>&1
```

Use private/local or private object storage for sensitive expense receipts. Validate the disk's access policy before production. Back up PostgreSQL and uploaded files regularly, encrypt backups, test restores, and retain copies outside the application host.

## Tests and quality

```bash
php artisan test --compact
vendor/bin/pint --format agent
composer audit
npm run build
```

Tests use an isolated SQLite database by default. Production and normal development are designed for PostgreSQL.

## Production deployment

Set `APP_ENV=production`, `APP_DEBUG=false`, a strong `APP_KEY`, HTTPS `APP_URL`, production database credentials, durable queue/cache/session drivers, and a configured mailer. Then run:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

Run the queue worker under a process supervisor, configure the scheduler, terminate TLS at the web server/load balancer, restrict database access, and establish database plus file-storage backups before launch.
