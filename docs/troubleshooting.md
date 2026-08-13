# Troubleshooting

## Local App Does Not Open

- Confirm containers are running:

```bash
docker compose ps
```

- Check app logs:

```bash
docker compose logs -f akaunting
```

## Installer Fails

- Confirm MySQL is healthy.
- Confirm the setup service has the same database env values as the app.
- Re-run:

```bash
docker compose run --rm setup
```

## Cannot Sign In

- Confirm the local admin email is `admin@akaunting.local`.
- Confirm the password is `password12345`.
- Confirm `APP_INSTALLED=true`.

## Portal SSO Fails

- Confirm Portal and Akaunting share the same `GRITCHI_SSO_SECRET`.
- Confirm the token audience is `akaunting`.
- Confirm the token issuer is `gritchi-portal`.
- Confirm the token has not expired.
- Confirm the user email is present in the token.

## Portal Does Not Receive Webhooks

- Confirm Portal is running on `http://localhost:3000`.
- Confirm `GRITCHI_PORTAL_WEBHOOK_URL`.
- Confirm `GRITCHI_PORTAL_FINANCE_WEBHOOK_URL`.
- Confirm `GRITCHI_WEBHOOK_SECRET` or `WEBHOOK_SHARED_SECRET`.
- Check Akaunting logs for `Gritchi contact webhook failed` or `Gritchi finance webhook failed`.

## Duplicate Sync Loops

- Portal-origin writes should include:

```text
X-Gritchi-Sync-Origin: portal
```

Akaunting skips outbound Gritchi webhooks when that header is present.

## Finance Change Does Not Trigger Webhook

- Confirm the record type is supported.
- Confirm the record has a contact email or Xero ID marker.
- Confirm account records are bank accounts.
- Confirm categories have a Xero account marker.
- Check logs for `Gritchi finance webhook skipped`.
