# Local Development

## Docker Setup

```bash
docker compose up -d mysql
docker compose run --rm setup
docker compose up -d akaunting
```

Open:

```text
http://localhost:8050
```

Default local admin:

- Email: `admin@akaunting.local`
- Password: `password12345`

## Direct Setup

Install dependencies:

```bash
composer install
npm install
npm run dev
```

Install Akaunting:

```bash
php artisan install --db-host=127.0.0.1 --db-port=3307 --db-name=akaunting --db-username=akaunting --db-password=password12345 --db-prefix=aka_ --company-name=Gritchi --company-email=admin@gritchi.local --admin-email=admin@akaunting.local --admin-password=password12345 --locale=en-GB --no-interaction
```

## Local URLs

- App root: `http://localhost:8050`
- Login: `http://localhost:8050/auth/login`
- Gritchi SSO: `http://localhost:8050/gritchi/sso?token=<token>`
- Admin dashboard: `http://localhost:8050/1`
- Client portal: `http://localhost:8050/1/portal`
- API ping: `http://localhost:8050/api/ping`

## Asset Commands

```bash
npm run dev
npm run watch
npm run prod
```

## Useful Docker Commands

```bash
docker compose ps
docker compose logs -f akaunting
docker compose logs -f mysql
docker compose exec akaunting php artisan route:list
docker compose exec akaunting php artisan tinker
```
