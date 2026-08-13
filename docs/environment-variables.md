# Environment Variables

## Core App

```env
APP_NAME=Akaunting
APP_ENV=local
APP_LOCALE=en-GB
APP_INSTALLED=true
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8050
APP_SCHEDULE_TIME="09:00"
```

## Database

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=akaunting
DB_USERNAME=akaunting
DB_PASSWORD=password12345
DB_PREFIX=aka_
```

## Cache, Session, Queue

```env
BROADCAST_DRIVER=log
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

## Mail

```env
MAIL_MAILER=mail
MAIL_HOST=localhost
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_NAME=null
MAIL_FROM_ADDRESS=null
```

## Gritchi

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

`GRITCHI_SSO_SECRET` must match Portal's `GRITCHI_SSO_SECRET`.

`GRITCHI_WEBHOOK_SECRET` or `WEBHOOK_SHARED_SECRET` must match Portal's `WEBHOOK_SHARED_SECRET`.

`GRITCHI_SKIP_USER_INVITATIONS=true` prevents Akaunting from sending its own user invitations for Portal-created users.

`GRITCHI_DISABLE_PLAN_LIMITS=true` disables plan-limit checks in local Gritchi development.
