# Deployment

Akaunting can be deployed with the Docker image built from this repository.

## Local Docker Files

- `Dockerfile.local`: local Apache/PHP image with built frontend assets.
- `docker-compose.yml`: local app, setup, and MySQL services.
- `docker/local-setup.sh`: runs the Akaunting installer with retry logic.

## Runtime Services

Local compose services:

- `akaunting`: web app on port `8050`.
- `setup`: one-off installer.
- `mysql`: MySQL 8 on host port `3307`.

## Build And Run

```bash
docker compose build
docker compose up -d mysql
docker compose run --rm setup
docker compose up -d akaunting
```

## Production Notes

For deployed environments:

- Set `APP_ENV=production`.
- Set `APP_DEBUG=false`.
- Set `APP_INSTALLED=true` after installation.
- Use a real `APP_KEY`.
- Use persistent database and storage volumes.
- Configure mail settings.
- Configure `APP_URL`.
- Configure the Gritchi Portal webhook URLs.
- Keep `GRITCHI_SSO_SECRET` in sync with Portal.

## Scheduled Commands

Akaunting's scheduler includes:

- Invoice reminders.
- Bill reminders.
- Recurring transaction checks.
- Temporary storage cleanup.
- Model pruning.

The schedule time is controlled by `APP_SCHEDULE_TIME`.
