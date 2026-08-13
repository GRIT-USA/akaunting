# Gritchi Akaunting Technical Documentation

Akaunting is the Finance workspace in the Gritchi platform. It handles accounting records such as customers, vendors, invoices, bills, accounts, categories, transactions, payments, and transfers.

This repository is based on Akaunting and includes Gritchi-specific integration work for Portal SSO and finance webhooks back into the Gritchi Portal.

## Environments

- Local app: `http://localhost:8050`
- Local MySQL: `localhost:3307`
- Gritchi Portal local webhook target: `http://host.docker.internal:3000`
- Gritchi Portal local app: `http://localhost:3000`

## Tech Stack

- PHP `8.1+`
- Laravel `10`
- Akaunting `3.1.x`
- MySQL or MariaDB
- Apache in the local Docker image
- Laravel Sanctum
- Laravel modules
- Laravel Mix
- Vue `2.7`
- Tailwind CSS
- Livewire
- Guzzle HTTP client
- PHPUnit
- Sentry and Bugsnag packages are available for error tracking

## Quick Start

Docker is the easiest local setup for this repository.

```bash
docker compose up -d mysql
docker compose run --rm setup
docker compose up -d akaunting
```

Then open:

```text
http://localhost:8050
```

Default local values from `docker-compose.yml`:

- Admin email: `admin@akaunting.local`
- Admin password: `password12345`
- Company name: `Gritchi`
- Company email: `admin@gritchi.local`
- Database: `akaunting`
- Database user: `akaunting`
- Database password: `password12345`

## Local Development Environment

Create or update `.env` for direct local development. Minimum useful values:

```env
APP_NAME=Akaunting
APP_ENV=local
APP_DEBUG=true
APP_INSTALLED=true
APP_URL=http://localhost:8050

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=akaunting
DB_USERNAME=akaunting
DB_PASSWORD=password12345
DB_PREFIX=aka_

QUEUE_CONNECTION=sync
CACHE_DRIVER=file
SESSION_DRIVER=file
```

Gritchi integration values:

```env
GRITCHI_SSO_SECRET=dev-gritchi-sso-secret-change-me
GRITCHI_SSO_ROLE=admin
GRITCHI_SSO_LOCALE=en-GB
GRITCHI_PORTAL_WEBHOOK_URL=http://host.docker.internal:3000/webhooks/akaunting
GRITCHI_PORTAL_FINANCE_WEBHOOK_URL=http://host.docker.internal:3000/webhooks/akaunting/finance
GRITCHI_WEBHOOK_SECRET=local-webhook-secret
WEBHOOK_SHARED_SECRET=local-webhook-secret
GRITCHI_DISABLE_PLAN_LIMITS=true
GRITCHI_SKIP_USER_INVITATIONS=true
```

Install dependencies for non-Docker development:

```bash
composer install
npm install
npm run dev
```

Install Akaunting manually:

```bash
php artisan install --db-host=127.0.0.1 --db-port=3307 --db-name=akaunting --db-username=akaunting --db-password=password12345 --db-prefix=aka_ --company-name=Gritchi --company-email=admin@gritchi.local --admin-email=admin@akaunting.local --admin-password=password12345 --locale=en-GB --no-interaction
```

## Useful Local URLs

- App root: `http://localhost:8050`
- Sign-in: `http://localhost:8050/auth/login`
- Gritchi SSO consume URL: `http://localhost:8050/gritchi/sso?token=<token>`
- Admin dashboard: `http://localhost:8050/1`
- Client portal dashboard: `http://localhost:8050/1/portal`
- API ping: `http://localhost:8050/api/ping`

## Common Commands

```bash
docker compose up -d mysql                  # Start local MySQL
docker compose run --rm setup               # Run local installer
docker compose up -d akaunting              # Start Akaunting
docker compose logs -f akaunting            # Follow app logs
composer install                            # Install PHP dependencies
npm install                                 # Install JS dependencies
npm run dev                                 # Build local assets
npm run watch                               # Watch assets
npm run prod                                # Build production assets
php artisan install                         # Run Akaunting installer
php artisan sample-data:seed                # Add sample data
php artisan test                            # Run tests
vendor/bin/phpunit                          # Run PHPUnit directly
php artisan route:list                      # List routes
php artisan tinker                          # Laravel console
```

## Current Finance Flow

1. A Portal user with the Finance role launches Akaunting.
2. Portal redirects to `gritchi/sso` with a signed token.
3. Akaunting validates the token with `GRITCHI_SSO_SECRET`.
4. Akaunting finds or creates the user by email.
5. Akaunting ensures the user belongs to a company and has the configured role.
6. The user is signed into Akaunting and redirected to the dashboard.
7. Finance record changes in Akaunting can post webhooks back to Portal.
8. Portal records the event and can sync selected changes onward to Xero when enabled.

## Integrations

Gritchi Portal:

- Launches Finance users into Akaunting through SSO.
- Sends Portal-origin API writes into Akaunting.
- Receives Akaunting profile and finance webhook payloads.

Xero:

- Portal imports Xero accounting data into Akaunting.
- Akaunting stores imported Xero IDs in fields such as notes, references, descriptions, and `created_from`.
- Akaunting finance observers use those IDs when sending changes back to Portal.

Customers and vendors:

- Customer profile changes can notify Portal.
- Customer and vendor finance changes can notify Portal when linked to Xero data.

Invoices, bills, and payments:

- Invoice and bill saves can notify Portal.
- Payment saves can notify Portal.
- Document item changes trigger document-level finance sync.

Accounts and categories:

- Bank account saves can notify Portal.
- Category saves can notify Portal when linked to a Xero account.

## Git Collaboration

Use these branch naming templates in this repository:

```text
Feature: feat/[brief-description]-[ticket-number]
Bugfix: bugfix/[brief-description]-[ticket-number]
Enhancement: enhance/[brief-description]-[ticket-number]
```

Use a short kebab-case description and keep the actual ticket number at the end.

Before pushing a final feature commit:

```bash
npm run dev
php artisan test
```

## Technical Documentation

Start with [docs/README.md](docs/README.md).

Key docs:

- [Architecture](docs/architecture.md)
- [Authentication And Syncing](docs/authentication-and-syncing.md)
- [Data Model](docs/data-model.md)
- [Local Development](docs/local-development.md)
- [Environment Variables](docs/environment-variables.md)
- [API Integration](docs/api-integration.md)
- [Deployment](docs/deployment.md)
- [Testing](docs/testing.md)
- [Maintenance](docs/maintenance.md)
- [Third-Party Services](docs/third-party-services.md)
- [Troubleshooting](docs/troubleshooting.md)
