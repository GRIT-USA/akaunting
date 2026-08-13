# Maintenance

## Regular Checks

- Confirm the app can reach MySQL.
- Confirm storage directories are writable.
- Confirm `APP_URL` matches the deployed URL.
- Confirm Portal webhook URLs are configured.
- Confirm `GRITCHI_SSO_SECRET` matches Portal.
- Check logs for failed Gritchi webhook posts.

## Logs

Application logs are stored under:

```text
storage/logs
```

Local Docker logs:

```bash
docker compose logs -f akaunting
docker compose logs -f mysql
```

## Cache And Config

Useful commands:

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## Scheduler

The Laravel scheduler handles reminders, recurring checks, cleanup, and pruning.

Schedule time comes from:

```env
APP_SCHEDULE_TIME="09:00"
```

## Gritchi Webhook Review

If Portal is not receiving finance updates, inspect:

- `GRITCHI_PORTAL_WEBHOOK_URL`
- `GRITCHI_PORTAL_FINANCE_WEBHOOK_URL`
- `GRITCHI_WEBHOOK_SECRET`
- `WEBHOOK_SHARED_SECRET`
- Latest Akaunting logs for `Gritchi finance webhook failed`
